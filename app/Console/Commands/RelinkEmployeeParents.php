<?php

namespace App\Console\Commands;

use App\Services\OdooService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class RelinkEmployeeParents extends Command
{
    protected $signature = 'employees:relink-parents
        {file=public/Master Sheet - HR-2026- DEVOLEPER.xlsx}
        {--sheet=Employee_Master1}
        {--header-row=7}
        {--dry-run : Show matches without writing to Odoo}';

    protected $description = 'Second-pass: fuzzy-match unwired parent_id links using the Master Sheet';

    public function handle(OdooService $odoo): int
    {
        $file = $this->argument('file');
        if (!str_starts_with($file, '/')) {
            $file = base_path($file);
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly([$this->option('sheet')]);
        $book = $reader->load($file);
        $sheet = $book->getSheetByName($this->option('sheet'));
        $headerRow = (int) $this->option('header-row');

        $sheetRows = [];
        $maxRow = $sheet->getHighestDataRow();
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $code = trim((string) $sheet->getCell([1, $r])->getValue());
            $name = trim((string) $sheet->getCell([2, $r])->getValue());
            $parentName = trim((string) $sheet->getCell([4, $r])->getValue());
            if ($code === '' || $name === '' || !preg_match('/^ID-\d+/i', $code)) {
                continue;
            }
            $sheetRows[] = ['code' => $code, 'name' => $name, 'parent_name' => $parentName];
        }
        $this->info("Sheet rows: " . count($sheetRows));

        $odooEmps = $odoo->searchRead('hr.employee', [['id', '!=', 1]], ['id', 'name', 'parent_id'], 0, 0, 'id asc');
        $byNameKey = [];
        $tokensIdx = [];
        foreach ($odooEmps as $e) {
            $key = $this->norm($e['name']);
            $byNameKey[$key] = $e['id'];
            $tokensIdx[] = ['id' => $e['id'], 'name' => $e['name'], 'tokens' => $this->tokens($e['name'])];
        }

        $linked = 0;
        $stillMissing = [];
        $dryRun = (bool) $this->option('dry-run');

        foreach ($sheetRows as $row) {
            if ($row['parent_name'] === '' || $row['parent_name'] === '0') {
                continue;
            }
            $childId = $byNameKey[$this->norm($row['name'])] ?? null;
            if (!$childId) {
                continue;
            }

            // Check current parent_id to avoid redundant writes
            $current = $this->findCurrentEmp($odooEmps, $childId);
            if ($current && !empty($current['parent_id'])) {
                continue; // already linked
            }

            $parentId = $this->matchParent($row['parent_name'], $byNameKey, $tokensIdx);

            if (!$parentId) {
                $stillMissing[] = "{$row['code']} {$row['name']} → '{$row['parent_name']}'";
                continue;
            }

            $parentName = $this->idToName($odooEmps, $parentId);
            $this->line("  {$row['code']} {$row['name']} → {$parentName} (id={$parentId})");

            if (!$dryRun) {
                try {
                    $odoo->write('hr.employee', [$childId], ['parent_id' => $parentId]);
                    $linked++;
                } catch (Throwable $e) {
                    $stillMissing[] = "{$row['code']} (write failed): " . $e->getMessage();
                }
            } else {
                $linked++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? "Would link " : "Linked ") . $linked);
        if ($stillMissing) {
            $this->warn("Still unmatched (" . count($stillMissing) . "):");
            foreach ($stillMissing as $m) {
                $this->line('  - ' . $m);
            }
        }
        return self::SUCCESS;
    }

    private function findCurrentEmp(array $emps, int $id): ?array
    {
        foreach ($emps as $e) {
            if ($e['id'] === $id) return $e;
        }
        return null;
    }

    private function idToName(array $emps, int $id): string
    {
        foreach ($emps as $e) {
            if ($e['id'] === $id) return $e['name'];
        }
        return "(id={$id})";
    }

    private function matchParent(string $parentName, array $byNameKey, array $tokensIdx): ?int
    {
        $key = $this->norm($parentName);
        if (isset($byNameKey[$key])) {
            return $byNameKey[$key];
        }

        $parentTokens = $this->tokens($parentName);
        if (!$parentTokens) return null;

        // All parent tokens must be present in candidate's tokens
        $candidates = [];
        foreach ($tokensIdx as $cand) {
            $hits = 0;
            foreach ($parentTokens as $t) {
                if (in_array($t, $cand['tokens'], true)) $hits++;
            }
            if ($hits === count($parentTokens) && $hits > 0) {
                $candidates[] = $cand;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0]['id'];
        }
        // Prefer shortest name when multiple candidates
        if (count($candidates) > 1) {
            usort($candidates, fn($a, $b) => strlen($a['name']) <=> strlen($b['name']));
            return $candidates[0]['id'];
        }

        // Levenshtein fallback for typos on single-token parent names
        $best = null;
        $bestDist = PHP_INT_MAX;
        foreach ($tokensIdx as $cand) {
            $dist = levenshtein($this->norm($parentName), $this->norm($cand['name']));
            if ($dist < $bestDist) { $bestDist = $dist; $best = $cand; }
        }
        if ($best && $bestDist <= 3) {
            return $best['id'];
        }
        return null;
    }

    private function norm(string $s): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
    }

    private function tokens(string $s): array
    {
        $s = $this->norm($s);
        return array_values(array_filter(explode(' ', $s), fn($t) => $t !== ''));
    }
}
