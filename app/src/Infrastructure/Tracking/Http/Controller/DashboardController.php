<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Http\Controller;

use App\Application\Tracking\Service\SyncService;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController
{
    public function __construct(
        private readonly SyncService $syncService,
        private readonly CompetitionRepositoryInterface $competitionRepository,
    ) {}

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargando...</title>
    <style>
        body { margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; background: #0f172a; font-family: sans-serif; }
        .spinner { width: 48px; height: 48px; border: 5px solid #334155; border-top-color: #38bdf8; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        p { color: #94a3b8; margin-top: 1rem; font-size: 0.9rem; }
        .container { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <p>Sincronizando datos...</p>
    </div>
    <script>
        fetch('/sync')
            .then(() => { window.location.href = '/dashboard'; })
            .catch(() => { window.location.href = '/dashboard'; });
    </script>
</body>
</html>
HTML;

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
    }

    #[Route('/sync', name: 'sync', methods: ['GET'])]
    public function sync(): JsonResponse
    {
        foreach ($this->competitionRepository->findAll() as $competition) {
            $this->syncService->sync($competition);
        }

        return new JsonResponse(['status' => 'ok']);
    }

}
