<?php
require_once 'config.php';

$pdo = getDBConnection();

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 6;
        $status = $_GET['status'] ?? 'active';

        $offset = ($page - 1) * $limit;

        if ($status === 'all') {
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects");
            $countStmt->execute();
        } else {
            $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM projects WHERE status = :status");
            $countStmt->execute(['status' => $status]);
        }
        $totalProjects = $countStmt->fetch()['total'];

        if ($status === 'all') {
            $stmt = $pdo->prepare("
                SELECT id, title, short_description, full_description, icon, tags, demo_url, github_url,
                       image_url, featured, order_index, status, created_at, updated_at
                FROM projects
                ORDER BY order_index ASC, created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, title, short_description, icon, tags, demo_url, github_url,
                       image_url, featured, order_index, created_at
                FROM projects
                WHERE status = :status
                ORDER BY order_index ASC, created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        $projects = $stmt->fetchAll();

        foreach ($projects as &$project) {
            $project['tags'] = json_decode($project['tags'], true);
        }

        echo json_encode([
            'success' => true,
            'data' => $projects,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalProjects,
                'total_pages' => ceil($totalProjects / $limit),
                'has_next' => $page < ceil($totalProjects / $limit),
                'has_prev' => $page > 1
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'get':
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project ID is required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id, title, short_description, full_description, icon, tags,
                   demo_url, github_url, image_url, featured, order_index,
                   status, created_at, updated_at
            FROM projects
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Project not found']);
            exit;
        }

        $project['tags'] = json_decode($project['tags'], true);

        echo json_encode([
            'success' => true,
            'data' => $project
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['title']) || !isset($data['short_description'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title and short description are required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects (
                    title, short_description, full_description, icon, tags,
                    demo_url, github_url, image_url, featured, order_index, status
                ) VALUES (
                    :title, :short_description, :full_description, :icon, :tags,
                    :demo_url, :github_url, :image_url, :featured, :order_index, :status
                )
            ");

            $stmt->execute([
                'title' => $data['title'],
                'short_description' => $data['short_description'],
                'full_description' => $data['full_description'] ?? null,
                'icon' => $data['icon'] ?? '🚀',
                'tags' => $data['tags'] ?? '[]',
                'demo_url' => $data['demo_url'] ?? null,
                'github_url' => $data['github_url'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'featured' => isset($data['featured']) ? intval($data['featured']) : 0,
                'order_index' => isset($data['order_index']) ? intval($data['order_index']) : 0,
                'status' => $data['status'] ?? 'active'
            ]);

            echo json_encode([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'message' => 'Project created successfully'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project ID is required']);
            exit;
        }

        if (!isset($data['title']) || !isset($data['short_description'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title and short description are required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE projects SET
                    title = :title,
                    short_description = :short_description,
                    full_description = :full_description,
                    icon = :icon,
                    tags = :tags,
                    demo_url = :demo_url,
                    github_url = :github_url,
                    image_url = :image_url,
                    featured = :featured,
                    order_index = :order_index,
                    status = :status
                WHERE id = :id
            ");

            $result = $stmt->execute([
                'id' => $data['id'],
                'title' => $data['title'],
                'short_description' => $data['short_description'],
                'full_description' => $data['full_description'] ?? null,
                'icon' => $data['icon'] ?? '🚀',
                'tags' => $data['tags'] ?? '[]',
                'demo_url' => $data['demo_url'] ?? null,
                'github_url' => $data['github_url'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'featured' => isset($data['featured']) ? intval($data['featured']) : 0,
                'order_index' => isset($data['order_index']) ? intval($data['order_index']) : 0,
                'status' => $data['status'] ?? 'active'
            ]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Project not found']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Project updated successfully'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete':
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Project ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id");
            $stmt->execute(['id' => $data['id']]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Project not found']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
