<?php

namespace App\Services;

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Imports a bank/card CSV export as expenses. Deliberately forgiving about
 * layout: the delimiter, header names and date format are detected.
 *
 * Sign convention: if the file contains any negative amounts, negatives are
 * spending and positives (refunds, deposits) are skipped; otherwise every
 * positive amount is treated as spending.
 */
class ExpenseCsvImporter
{
    public const MAX_ROWS = 2000;

    private const HEADERS = [
        'date' => ['date', 'transaction date', 'posted date', 'post date', 'posting date', 'trans date'],
        'name' => ['description', 'name', 'payee', 'merchant', 'memo', 'details', 'transaction description'],
        'amount' => ['amount', 'value', 'transaction amount'],
        'debit' => ['debit', 'withdrawal', 'withdrawals', 'money out'],
        'category' => ['category'],
    ];

    /**
     * @return array{imported: int, duplicates: int, skipped: int, errors: list<string>}
     */
    public function import(User $owner, string $csv): array
    {
        $rows = $this->parse($csv);
        $categories = BudgetCategory::where('user_id', $owner->id)->get();
        $fallback = $categories->first(fn ($c) => strcasecmp($c->name, 'Other') === 0) ?? $categories->first();

        if (! $fallback) {
            throw new InvalidArgumentException('Create a budget category before importing.');
        }

        $byName = $categories->keyBy(fn ($c) => mb_strtolower($c->name));
        $hasNegatives = collect($rows)->contains(fn ($r) => $r['amount'] < 0);

        $result = ['imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $row) {
            $amount = $hasNegatives ? ($row['amount'] < 0 ? abs($row['amount']) : 0.0) : abs($row['amount']);

            if ($amount <= 0) {
                $result['skipped']++;

                continue;
            }

            $exists = Expense::where('user_id', $owner->id)
                ->whereDate('date', $row['date'])
                ->where('name', $row['name'])
                ->where('amount', $amount)
                ->exists();

            if ($exists) {
                $result['duplicates']++;

                continue;
            }

            $category = $byName[mb_strtolower($row['category'] ?? '')] ?? $fallback;

            $owner->expenses()->create([
                'name' => mb_substr($row['name'], 0, 255),
                'amount' => $amount,
                'date' => $row['date'],
                'budget_category_id' => $category->id,
            ]);
            $result['imported']++;
        }

        $result['errors'] = $this->errors;

        return $result;
    }

    /** @var list<string> */
    private array $errors = [];

    /**
     * @return list<array{date: string, name: string, amount: float, category: string|null}>
     */
    private function parse(string $csv): array
    {
        $this->errors = [];
        $csv = ltrim($csv, "\xEF\xBB\xBF"); // BOM from Excel
        $firstLine = strtok($csv, "\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($d) => substr_count($firstLine, $d))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if (! $header) {
            throw new InvalidArgumentException('The file is empty.');
        }

        $map = $this->mapColumns($header);
        if (! isset($map['date'], $map['name']) || (! isset($map['amount']) && ! isset($map['debit']))) {
            throw new InvalidArgumentException('Could not find date, description and amount columns in the header row.');
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new InvalidArgumentException('Too many rows (limit '.self::MAX_ROWS.'). Split the file and import it in parts.');
            }

            try {
                $date = Carbon::parse(trim($cells[$map['date']] ?? ''))->toDateString();
                $name = trim((string) ($cells[$map['name']] ?? ''));
                $raw = isset($map['amount']) ? ($cells[$map['amount']] ?? '') : ($cells[$map['debit']] ?? '');
                $amount = $this->amount((string) $raw);
            } catch (\Throwable) {
                if (count($this->errors) < 5) {
                    $this->errors[] = "Line {$line} could not be read.";
                }

                continue;
            }

            if ($name === '') {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'name' => $name,
                'amount' => $amount,
                'category' => isset($map['category']) ? trim((string) ($cells[$map['category']] ?? '')) : null,
            ];
        }
        fclose($stream);

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $title) {
            $title = mb_strtolower(trim((string) $title));
            foreach (self::HEADERS as $field => $names) {
                if (! isset($map[$field]) && in_array($title, $names, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return $map;
    }

    private function amount(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('empty');
        }
        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        // "12,50" is a decimal comma; "1,250" / "1,250.00" use commas as thousands separators.
        $decimalComma = ! str_contains($raw, '.') && preg_match('/,\d{1,2}\)?$/', $raw) === 1;
        $clean = preg_replace('/[^0-9.\-]/', '', $decimalComma ? str_replace(',', '.', $raw) : str_replace(',', '', $raw));
        if ($clean === '' || ! is_numeric($clean)) {
            throw new InvalidArgumentException('bad amount');
        }

        return $negative ? -abs((float) $clean) : (float) $clean;
    }
}
