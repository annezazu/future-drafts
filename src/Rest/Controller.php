<?php
declare(strict_types=1);

namespace FutureDrafts\Rest;

use FutureDrafts\PostMeta;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Controller
{
    public const NAMESPACE = 'future-drafts/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/entries', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list'],
                'permission_callback' => [$this, 'canEditPosts'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this, 'canEditPosts'],
                'args'                => [
                    'title'      => ['type' => 'string', 'default' => ''],
                    'content'    => ['type' => 'string', 'default' => ''],
                    'remind_on'  => ['type' => 'string', 'required' => true],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/entries/(?P<id>\d+)/snooze', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'snooze'],
            'permission_callback' => [$this, 'canEditPosts'],
            'args'                => [
                'remind_on' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/entries/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete'],
            'permission_callback' => [$this, 'canEditPosts'],
        ]);
    }

    public function canEditPosts(): bool
    {
        return current_user_can('edit_posts');
    }

    public function list(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        $today = $this->today();

        $query = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'draft',
            'author'         => $userId,
            'posts_per_page' => 200,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => PostMeta::KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $due = [];
        $pending = [];
        foreach ($query->posts as $post) {
            $entry = $this->shape($post);
            if ($entry['remind_on'] !== '' && $entry['remind_on'] <= $today) {
                $due[] = $entry;
            } else {
                $pending[] = $entry;
            }
        }

        usort($due, static fn ($a, $b) => strcmp($a['remind_on'], $b['remind_on']));
        usort($pending, static fn ($a, $b) => strcmp($a['remind_on'], $b['remind_on']));

        return new WP_REST_Response([
            'due'     => $due,
            'pending' => $pending,
        ]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $title   = trim((string) $request->get_param('title'));
        $content = trim((string) $request->get_param('content'));
        $date    = PostMeta::sanitize($request->get_param('remind_on'));

        if ($title === '' && $content === '') {
            return new WP_Error('future_drafts_empty', __('Add a title or some notes.', 'future-drafts'), ['status' => 400]);
        }
        if ($date === '') {
            return new WP_Error('future_drafts_invalid_date', __('Pick a valid date.', 'future-drafts'), ['status' => 400]);
        }

        $postId = wp_insert_post([
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $content,
            'post_author'  => get_current_user_id(),
        ], true);

        if ($postId instanceof WP_Error) {
            return $postId;
        }

        update_post_meta($postId, PostMeta::KEY, $date);

        return new WP_REST_Response($this->shape(get_post($postId)), 201);
    }

    public function snooze(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->ownedOr403((int) $request['id']);
        if ($post instanceof WP_Error) {
            return $post;
        }

        $date = PostMeta::sanitize($request->get_param('remind_on'));
        if ($date === '') {
            return new WP_Error('future_drafts_invalid_date', __('Pick a valid date.', 'future-drafts'), ['status' => 400]);
        }

        update_post_meta($post->ID, PostMeta::KEY, $date);

        return new WP_REST_Response($this->shape(get_post($post->ID)));
    }

    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = $this->ownedOr403((int) $request['id']);
        if ($post instanceof WP_Error) {
            return $post;
        }

        $result = wp_trash_post($post->ID);
        if (!$result) {
            return new WP_Error('future_drafts_trash_failed', __('Could not delete this draft.', 'future-drafts'), ['status' => 500]);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $post->ID]);
    }

    private function ownedOr403(int $id): WP_Post|WP_Error
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'post') {
            return new WP_Error('future_drafts_not_found', __('Draft not found.', 'future-drafts'), ['status' => 404]);
        }
        if ((int) $post->post_author !== get_current_user_id()) {
            return new WP_Error('future_drafts_forbidden', __('Not your draft.', 'future-drafts'), ['status' => 403]);
        }
        if (get_post_meta($post->ID, PostMeta::KEY, true) === '') {
            return new WP_Error('future_drafts_not_found', __('Draft not found.', 'future-drafts'), ['status' => 404]);
        }
        return $post;
    }

    /**
     * @return array{id:int,title:string,excerpt:string,remind_on:string,edit_url:string,status:string}
     */
    private function shape(WP_Post $post): array
    {
        $remindOn = (string) get_post_meta($post->ID, PostMeta::KEY, true);
        $today = $this->today();
        $content = wp_strip_all_tags((string) $post->post_content);

        return [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'excerpt'   => wp_trim_words($content, 24),
            'remind_on' => $remindOn,
            'edit_url'  => (string) get_edit_post_link($post->ID, 'raw'),
            'status'    => ($remindOn !== '' && $remindOn <= $today) ? 'due' : 'pending',
        ];
    }

    private function today(): string
    {
        return wp_date('Y-m-d');
    }
}
