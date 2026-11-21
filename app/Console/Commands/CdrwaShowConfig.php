<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CdrwaShowConfig extends Command
{
    protected $signature = 'cdrwa:show-config';

    protected $description = 'Print the resolved CDRWA paths and whether each one exists';

    public function handle(): int
    {
        $pathKeys = [
            'site_path',
            'template_path',
            'assets_path',
            'static_path',
            'registry_path',
            'seed_data_path',
            'data_path',
            'backup_path',
            'output_path',
        ];

        $rows = [];
        foreach ($pathKeys as $key) {
            $value = config('cdrwa.' . $key);

            if ($value === null || $value === '') {
                $rows[] = [$key, 'NOT SET', '-'];
                continue;
            }

            $rows[] = [$key, (string) $value, file_exists((string) $value) ? 'exists' : 'missing'];
        }

        $this->table(['Key', 'Value', 'Status'], $rows);

        $siteUrl = config('cdrwa.site_url');
        $adminEmail = config('cdrwa.admin_email');
        $adminPassword = config('cdrwa.admin_password');

        $this->line('site_url:       ' . (($siteUrl === null || $siteUrl === '') ? 'NOT SET' : $siteUrl));
        $this->line('admin_email:    ' . (($adminEmail === null || $adminEmail === '') ? 'NOT SET' : $adminEmail));
        $this->line('admin_password: ' . (($adminPassword === null || $adminPassword === '') ? 'NOT SET' : 'set (hidden)'));

        return self::SUCCESS;
    }
}