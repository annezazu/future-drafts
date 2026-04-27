<?php
declare(strict_types=1);

namespace FutureDrafts\Tests\Rest;

use FutureDrafts\PostMeta;
use FutureDrafts\Rest\Controller;
use WP_REST_Request;
use WP_UnitTestCase;

final class ControllerTest extends WP_UnitTestCase
{
    private int $authorId;
    private int $otherAuthorId;

    protected function setUp(): void
    {
        parent::setUp();
        (new Controller())->register();
        do_action('rest_api_init');

        $this->authorId = self::factory()->user->create(['role' => 'author']);
        $this->otherAuthorId = self::factory()->user->create(['role' => 'author']);
        wp_set_current_user($this->authorId);
    }

    private function makeFutureDraft(int $authorId, string $remindOn, string $title = 'Trip'): int
    {
        $postId = self::factory()->post->create([
            'post_status' => 'draft',
            'post_author' => $authorId,
            'post_title'  => $title,
        ]);
        update_post_meta($postId, PostMeta::KEY, $remindOn);
        return $postId;
    }

    public function test_list_returns_only_current_users_drafts(): void
    {
        $mine = $this->makeFutureDraft($this->authorId, '2030-01-01', 'Mine');
        $theirs = $this->makeFutureDraft($this->otherAuthorId, '2030-01-01', 'Theirs');

        $response = rest_do_request(new WP_REST_Request('GET', '/' . Controller::NAMESPACE . '/entries'));
        $data = $response->get_data();

        $titles = array_column(array_merge($data['due'], $data['pending']), 'title');
        $this->assertContains('Mine', $titles);
        $this->assertNotContains('Theirs', $titles);
    }

    public function test_list_splits_due_and_pending_by_today(): void
    {
        $today = wp_date('Y-m-d');
        $past = $this->makeFutureDraft($this->authorId, '2000-01-01', 'Past');
        $future = $this->makeFutureDraft($this->authorId, '2099-01-01', 'Future');
        $todayPost = $this->makeFutureDraft($this->authorId, $today, 'Today');

        $response = rest_do_request(new WP_REST_Request('GET', '/' . Controller::NAMESPACE . '/entries'));
        $data = $response->get_data();

        $dueIds = array_column($data['due'], 'id');
        $pendingIds = array_column($data['pending'], 'id');

        $this->assertContains($past, $dueIds);
        $this->assertContains($todayPost, $dueIds);
        $this->assertContains($future, $pendingIds);
    }

    public function test_create_persists_post_and_meta(): void
    {
        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries');
        $request->set_param('title', 'Lisbon');
        $request->set_param('content', 'Notes');
        $request->set_param('remind_on', '2030-06-14');

        $response = rest_do_request($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $post = get_post($data['id']);
        $this->assertSame('Lisbon', $post->post_title);
        $this->assertSame('draft', $post->post_status);
        $this->assertSame((string) $this->authorId, (string) $post->post_author);
        $this->assertSame('2030-06-14', get_post_meta($post->ID, PostMeta::KEY, true));
    }

    public function test_create_rejects_when_title_and_content_are_empty(): void
    {
        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries');
        $request->set_param('title', '');
        $request->set_param('content', '');
        $request->set_param('remind_on', '2030-06-14');

        $response = rest_do_request($request);

        $this->assertSame(400, $response->get_status());
    }

    public function test_create_rejects_invalid_date(): void
    {
        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries');
        $request->set_param('title', 'X');
        $request->set_param('remind_on', 'tomorrow');

        $response = rest_do_request($request);

        $this->assertSame(400, $response->get_status());
    }

    public function test_snooze_updates_meta(): void
    {
        $postId = $this->makeFutureDraft($this->authorId, '2030-01-01');

        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries/' . $postId . '/snooze');
        $request->set_param('remind_on', '2031-02-15');
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('2031-02-15', get_post_meta($postId, PostMeta::KEY, true));
    }

    public function test_snooze_returns_403_for_other_users_draft(): void
    {
        $postId = $this->makeFutureDraft($this->otherAuthorId, '2030-01-01');

        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries/' . $postId . '/snooze');
        $request->set_param('remind_on', '2031-02-15');
        $response = rest_do_request($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_snooze_returns_404_for_post_without_meta(): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'draft',
            'post_author' => $this->authorId,
        ]);

        $request = new WP_REST_Request('POST', '/' . Controller::NAMESPACE . '/entries/' . $postId . '/snooze');
        $request->set_param('remind_on', '2031-02-15');
        $response = rest_do_request($request);

        $this->assertSame(404, $response->get_status());
    }

    public function test_delete_trashes_post(): void
    {
        $postId = $this->makeFutureDraft($this->authorId, '2030-01-01');

        $request = new WP_REST_Request('DELETE', '/' . Controller::NAMESPACE . '/entries/' . $postId);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('trash', get_post_status($postId));
    }

    public function test_delete_returns_403_for_other_users_draft(): void
    {
        $postId = $this->makeFutureDraft($this->otherAuthorId, '2030-01-01');

        $request = new WP_REST_Request('DELETE', '/' . Controller::NAMESPACE . '/entries/' . $postId);
        $response = rest_do_request($request);

        $this->assertSame(403, $response->get_status());
    }

    public function test_endpoints_require_edit_posts_capability(): void
    {
        $subscriberId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($subscriberId);

        $response = rest_do_request(new WP_REST_Request('GET', '/' . Controller::NAMESPACE . '/entries'));
        $this->assertSame(403, $response->get_status());
    }
}
