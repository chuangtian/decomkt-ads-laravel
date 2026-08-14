<?php

namespace App\Console\Commands;

use App\Services\Integrations\FeishuConnector;
use App\Services\Integrations\Ga4Connector;
use App\Services\Integrations\GscConnector;
use App\Services\Integrations\IntegrationTestResult;
use Illuminate\Console\Command;
use Throwable;

class TestIntegrationsCommand extends Command
{
    protected $signature = 'integrations:test {platform? : feishu, gsc, ga4, or all} {--store=default-store}';

    protected $description = 'Test external integrations without displaying credential values';

    public function handle(FeishuConnector $feishu, GscConnector $gsc, Ga4Connector $ga4): int
    {
        $platform = strtolower((string) ($this->argument('platform') ?: 'all'));
        $connectors = ['feishu' => $feishu, 'gsc' => $gsc, 'ga4' => $ga4];
        if ($platform !== 'all' && ! isset($connectors[$platform])) {
            $this->error('Supported platforms: feishu, gsc, ga4, all.');

            return self::INVALID;
        }

        $failed = false;
        foreach ($platform === 'all' ? $connectors : [$platform => $connectors[$platform]] as $name => $connector) {
            try {
                /** @var IntegrationTestResult $result */
                $result = $connector->test((string) $this->option('store'));
                $this->line(json_encode([
                    'platform' => $result->platform,
                    'ok' => $result->ok,
                    'message' => $result->message,
                    'details' => $result->details,
                ], JSON_THROW_ON_ERROR));
                $failed = $failed || ! $result->ok;
            } catch (Throwable $exception) {
                $this->line(json_encode(['platform' => $name, 'ok' => false, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
