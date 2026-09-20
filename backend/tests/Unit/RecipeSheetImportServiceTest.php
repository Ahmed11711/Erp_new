<?php

namespace Tests\Unit;

use App\Services\Items\RecipeSheetImportService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RecipeSheetImportServiceTest extends TestCase
{
    public function test_parse_reads_all_worksheets_when_sheet_name_is_not_provided(): void
    {
        $path = $this->createMultiSheetWorkbook();

        $service = app(RecipeSheetImportService::class);
        $parsed = $service->parse($path);

        $this->assertCount(2, $parsed->recipes);
        $this->assertSame(['1', '2'], $parsed->parsedSheetNames);
        $this->assertSame('Product A', $parsed->recipes[0]['recipe_name']);
        $this->assertSame('Product B', $parsed->recipes[1]['recipe_name']);
    }

    public function test_parse_reads_only_named_worksheet_when_sheet_name_is_provided(): void
    {
        $path = $this->createMultiSheetWorkbook();

        $service = app(RecipeSheetImportService::class);
        $parsed = $service->parse($path, '2');

        $this->assertCount(1, $parsed->recipes);
        $this->assertSame(['2'], $parsed->parsedSheetNames);
        $this->assertSame('Product B', $parsed->recipes[0]['recipe_name']);
    }

    public function test_parse_ignores_color_column_and_does_not_create_colored_items(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['اسم الصنف', 'الخامات', 'الكمية', 'الوحدة', 'سعر', 'اللون'],
            ['Colored Product', 'Fabric A', 2, 'متر', 10, 'Black Marble Finish'],
            ['', 'Zipper', 1, 'وحده', 5, 'Maroon'],
        ]);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'recipe-import-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $service = app(RecipeSheetImportService::class);
        $parsed = $service->parse($path);

        $this->assertCount(1, $parsed->recipes);
        $this->assertSame([], $parsed->recipes[0]['finish_colors']);
        $this->assertNull($parsed->recipes[0]['ingredients'][0]['color']);
        $this->assertNull($parsed->recipes[0]['ingredients'][1]['color']);
    }

    private function createMultiSheetWorkbook(): string
    {
        $spreadsheet = new Spreadsheet();

        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('1');
        $this->fillRecipeSheet($sheet1, 'Product A', 'Fabric A', 2);

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('2');
        $this->fillRecipeSheet($sheet2, 'Product B', 'Fabric B', 3);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'recipe-import-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function fillRecipeSheet($sheet, string $productName, string $materialName, float $qty): void
    {
        $sheet->fromArray([
            ['اسم الصنف', 'الخامات', 'الكمية', 'الوحدة', 'سعر'],
            [$productName, $materialName, $qty, 'متر', 10],
        ]);
    }
}
