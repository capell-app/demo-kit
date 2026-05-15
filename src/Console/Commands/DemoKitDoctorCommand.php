<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands;

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Data\Diagnostics\DoctorReportData;
use Capell\DemoKit\Actions\Diagnostics\AssertDefaultDemoInstallHealthAction;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

final class DemoKitDoctorCommand extends Command
{
    protected $signature = 'capell:demo-kit-doctor
        {--json : Output a machine-readable JSON health report}';

    protected $description = 'Run Demo Kit health checks.';

    public function handle(): int
    {
        $checks = AssertDefaultDemoInstallHealthAction::run()->checks;
        $report = new DoctorReportData(
            status: $checks->every(fn (DoctorCheckResultData $check): bool => $check->passed) ? 'passed' : 'failed',
            checks: $checks->values(),
        );

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $report->passed() ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=blue;options=bold>Demo Kit Health Check</>');
        $this->newLine();

        $report->checks->each(function (DoctorCheckResultData $check): void {
            $this->outputCheckResult($check);
        });
        $this->newLine();

        if ($report->passed()) {
            $this->info('All checks passed.');

            return CommandAlias::SUCCESS;
        }

        $this->error('One or more checks failed. See suggestions above.');

        return CommandAlias::FAILURE;
    }

    private function outputCheckResult(DoctorCheckResultData $check): void
    {
        $icon = $check->passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $message = $check->message;

        if (! $check->passed && $check->remediation !== null && $check->remediation !== '') {
            $message .= ' ' . $check->remediation;
        }

        $this->components->twoColumnDetail(
            sprintf('%s %s', $icon, $check->label),
            $check->passed ? '<fg=green>' . $message . '</>' : '<fg=red>' . $message . '</>',
        );
    }
}
