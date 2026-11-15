<?php

declare(strict_types=1);

switch ($_SERVER['REQUEST_URI']) {
    case '/ok':
        echo '<html>Calendar</html>';
        break;
    case '/redirect':
        header('Location: /ok', true, 302);
        break;
    case '/rate-limited':
        http_response_code(429);
        break;
    case '/error':
        http_response_code(500);
        break;
    default:
        http_response_code(404);
}
