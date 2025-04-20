<?php

namespace App\Services;

use App\Models\File;
use App\Models\Record;
use App\Models\Status;
use App\Models\User;

/**
 * Servicio de Autentificación
 */
class AuthService
{
    public function canApprove(User $user, ?int $subProcessId): bool
    {
        return $user->hasRole('super_admin') ||
               ($user->hasRole('pro') && $user->validSubProcess($subProcessId));
    }

    public function canPending(User $user, File $file): bool
    {
        return $user->hasRole('super_admin') ||
               $file->user_id === $user->id;
    }

    public function canAccessSubProcessId(int|string|null $subProcessId): bool
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (is_null($subProcessId)) {
            return false;
        }

        return $user->validSubProcess($subProcessId);
    }

    public function validatedData(array $data): array
    {
        $user = auth()->user();

        $record = Record::with('subProcess')->findOrFail($data['record_id']);

        $hasApprovalAccess = $this->canApprove(
            $user,
            $record->sub_process_id ?? null
        );

        $statusApproved = Status::byTitle('Approved');
        $statusDraft = Status::byTitle('Draft');

        $lastVersion = File::where('record_id', $data['record_id'])
            ->orderByDesc('version')
            ->first();

        if ($lastVersion) {
            if ($hasApprovalAccess) {
                // Extrae la parte entera de la versión y suma 1
                $major = (int) $lastVersion->version;
                $newVersion = ($major + 1).'.0';

            } else {

                // Incrementa decimal en 0.1
                $newVersion = bcadd($lastVersion->version, '0.1', 1);
            }

        } else {

            $newVersion = $hasApprovalAccess ? '1.0' : '0.1';
        }

        $data['status_id'] = $hasApprovalAccess ? $statusApproved->id : $statusDraft->id;
        $data['version'] = $newVersion;
        $data['user_id'] = $user->id;

        return $data;
    }
}
