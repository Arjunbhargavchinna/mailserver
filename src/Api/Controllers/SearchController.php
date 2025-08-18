<?php

declare(strict_types=1);

namespace MailFlow\Api\Controllers;

use MailFlow\Core\Search\SearchManager;
use MailFlow\Core\Router\Response;

class SearchController
{
    private SearchManager $search;

    public function __construct(SearchManager $search)
    {
        $this->search = $search;
    }

    public function search(): Response
    {
        $user = $_REQUEST['auth_user'];
        $query = $_GET['q'] ?? '';
        $index = $_GET['index'] ?? 'emails';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
        $from = ($page - 1) * $limit;

        if (empty($query)) {
            return new Response(['error' => 'Search query is required'], 400);
        }

        $options = [
            'index' => $index,
            'size' => $limit,
            'from' => $from,
            'filters' => [
                'bool' => [
                    'should' => [
                        ['term' => ['sender_id' => $user['id']]],
                        ['term' => ['recipient_id' => $user['id']]],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        ];

        // Add date filter if provided
        if (!empty($_GET['date_from']) || !empty($_GET['date_to'])) {
            $dateFilter = ['range' => ['created_at' => []]];
            
            if (!empty($_GET['date_from'])) {
                $dateFilter['range']['created_at']['gte'] = $_GET['date_from'];
            }
            
            if (!empty($_GET['date_to'])) {
                $dateFilter['range']['created_at']['lte'] = $_GET['date_to'];
            }
            
            $options['filters']['bool']['must'][] = $dateFilter;
        }

        // Add sender filter if provided
        if (!empty($_GET['sender'])) {
            $options['filters']['bool']['must'][] = [
                'term' => ['sender_email' => $_GET['sender']]
            ];
        }

        // Add has attachments filter if provided
        if (!empty($_GET['has_attachments'])) {
            $options['filters']['bool']['must'][] = [
                'exists' => ['field' => 'attachments']
            ];
        }

        // Add sorting
        if (!empty($_GET['sort'])) {
            $sortField = $_GET['sort'];
            $sortOrder = $_GET['order'] ?? 'desc';
            
            $options['sort'] = [
                $sortField => ['order' => $sortOrder]
            ];
        } else {
            $options['sort'] = [
                'created_at' => ['order' => 'desc']
            ];
        }

        try {
            $result = $this->search->search($query, $options);
            
            return new Response([
                'data' => $result->getHits(),
                'total' => $result->getTotal(),
                'took' => $result->took,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $result->getTotal(),
                    'last_page' => ceil($result->getTotal() / $limit),
                ],
                'aggregations' => $result->aggregations,
            ]);
            
        } catch (\Exception $e) {
            return new Response(['error' => 'Search failed'], 500);
        }
    }

    public function suggestions(): Response
    {
        $query = $_GET['q'] ?? '';
        $field = $_GET['field'] ?? 'subject';
        $size = min(10, max(1, (int) ($_GET['size'] ?? 5)));

        if (empty($query)) {
            return new Response(['suggestions' => []]);
        }

        try {
            $suggestions = $this->search->suggest($query, [
                'field' => $field,
                'size' => $size,
            ]);
            
            return new Response(['suggestions' => $suggestions]);
            
        } catch (\Exception $e) {
            return new Response(['suggestions' => []]);
        }
    }
}