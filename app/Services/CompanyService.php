<?php

namespace App\Services;

use App\Models\Domain\Person\Company;
use App\Models\Domain\Person\CompanyContact;
use App\Models\Domain\Person\CompanyEmail;
use InvalidArgumentException;

class CompanyService
{
    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<int, array<string, mixed>>  $emails
     */
    public function syncContactsAndEmails(Company $company, array $contacts, array $emails): void
    {
        $normalizedContacts = $this->normalizeContacts($contacts);
        $normalizedEmails = $this->normalizeEmails($emails);

        $company->contacts()->delete();
        $company->emails()->delete();

        foreach ($normalizedContacts as $contact) {
            $company->contacts()->create($contact);
        }

        foreach ($normalizedEmails as $email) {
            $company->emails()->create($email);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     * @return list<array{nome: string, cargo: ?string, telefone: ?string, principal: bool}>
     */
    public function normalizeContacts(array $contacts): array
    {
        $rows = [];

        foreach ($contacts as $contact) {
            $nome = trim((string) ($contact['nome'] ?? ''));
            $cargo = trim((string) ($contact['cargo'] ?? ''));
            $telefone = trim((string) ($contact['telefone'] ?? ''));

            if ($nome === '' && $cargo === '' && $telefone === '') {
                continue;
            }

            if ($nome === '') {
                throw new InvalidArgumentException('Informe o nome de cada contato da empresa.');
            }

            $rows[] = [
                'nome' => $nome,
                'cargo' => $cargo !== '' ? $cargo : null,
                'telefone' => $telefone !== '' ? $telefone : null,
                'principal' => (bool) ($contact['principal'] ?? false),
            ];
        }

        return $this->ensureSinglePrincipal($rows, 'principal');
    }

    /**
     * @param  array<int, array<string, mixed>>  $emails
     * @return list<array{email: string, rotulo: ?string, principal: bool}>
     */
    public function normalizeEmails(array $emails): array
    {
        $rows = [];

        foreach ($emails as $emailRow) {
            $email = trim((string) ($emailRow['email'] ?? ''));
            $rotulo = trim((string) ($emailRow['rotulo'] ?? ''));

            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("E-mail inválido: {$email}");
            }

            $rows[] = [
                'email' => $email,
                'rotulo' => $rotulo !== '' ? $rotulo : null,
                'principal' => (bool) ($emailRow['principal'] ?? false),
            ];
        }

        return $this->ensureSinglePrincipal($rows, 'principal');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function ensureSinglePrincipal(array $rows, string $key): array
    {
        if ($rows === []) {
            return [];
        }

        $principalIndexes = [];

        foreach ($rows as $index => $row) {
            if (! empty($row[$key])) {
                $principalIndexes[] = $index;
            }
        }

        if ($principalIndexes === []) {
            $rows[0][$key] = true;

            return $rows;
        }

        $firstPrincipal = $principalIndexes[0];

        foreach ($rows as $index => $row) {
            $rows[$index][$key] = $index === $firstPrincipal;
        }

        return $rows;
    }

    /** @return array{nome: string, cargo: string, telefone: string, principal: bool} */
    public function emptyContactRow(): array
    {
        return [
            'nome' => '',
            'cargo' => '',
            'telefone' => '',
            'principal' => false,
        ];
    }

    /** @return array{email: string, rotulo: string, principal: bool} */
    public function emptyEmailRow(): array
    {
        return [
            'email' => '',
            'rotulo' => '',
            'principal' => false,
        ];
    }

    /** @return list<array{nome: string, cargo: string, telefone: string, principal: bool}> */
    public function contactsToForm(Company $company): array
    {
        $contacts = $company->contacts()
            ->orderByDesc('principal')
            ->orderBy('nome')
            ->get()
            ->map(fn (CompanyContact $contact) => [
                'nome' => $contact->nome,
                'cargo' => $contact->cargo ?? '',
                'telefone' => $contact->telefone ?? '',
                'principal' => $contact->principal,
            ])
            ->all();

        return $contacts !== [] ? $contacts : [$this->emptyContactRow()];
    }

    /** @return list<array{email: string, rotulo: string, principal: bool}> */
    public function emailsToForm(Company $company): array
    {
        $emails = $company->emails()
            ->orderByDesc('principal')
            ->orderBy('email')
            ->get()
            ->map(fn (CompanyEmail $email) => [
                'email' => $email->email,
                'rotulo' => $email->rotulo ?? '',
                'principal' => $email->principal,
            ])
            ->all();

        return $emails !== [] ? $emails : [$this->emptyEmailRow()];
    }
}
