<?php

namespace Modules\Projects\Support;

use Modules\Projects\Models\Project;

class ProjectCodeGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LENGTH = 6;

    public function generate(?int $workspaceId = null): string
    {
        do {
            $code = $this->randomCode();
        } while ($this->exists($code, $workspaceId));

        return $code;
    }

    public function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper(preg_replace('/[\s\-]+/', '', trim($value)) ?? '');
    }

    public function exists(string $code, ?int $workspaceId = null): bool
    {
        $query = Project::query()->where('code', $code);

        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->exists();
    }

    protected function randomCode(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
