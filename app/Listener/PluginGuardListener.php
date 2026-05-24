<?php

declare(strict_types=1);

namespace App\Listener;

use App\Model\Plugin;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\HttpServer\Event\RequestReceived;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

final class PluginGuardListener implements ListenerInterface
{
    private ?array $disabledMap = null;
    private int $lastFetch = 0;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function listen(): array
    {
        return [RequestReceived::class];
    }

    public function process(object $event): void
    {
        if (! $event instanceof RequestReceived) {
            return;
        }

        $path = $event->request->getUri()->getPath();
        $disabledMap = $this->getDisabledMap();

        foreach ($disabledMap as $code => $disabled) {
            if (str_contains($path, '/' . $code)) {
                $response = $this->container->get(ResponseInterface::class);
                $event->response = $response->json([
                    'code' => 403,
                    'message' => "插件「{$code}」已停用",
                    'data' => [],
                ])->withStatus(403);
                $event->propagationStopped = true;

                return;
            }
        }
    }

    private function getDisabledMap(): array
    {
        $now = time();
        if ($this->disabledMap !== null && ($now - $this->lastFetch) < 60) {
            return $this->disabledMap;
        }
        $this->disabledMap = Plugin::query()->where('status', 2)
            ->pluck('code')->mapWithKeys(fn (string $c) => [$c => true])->toArray();
        $this->lastFetch = $now;

        return $this->disabledMap;
    }
}
