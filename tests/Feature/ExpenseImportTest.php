<?php

use App\Models\BudgetCategory;
use App\Models\Expense;
use Illuminate\Http\UploadedFile;

function importCsv(string $csv, $user)
{
    $file = UploadedFile::fake()->createWithContent('bank.csv', $csv);

    return test()->actingAs($user)->post('/expenses/import', ['file' => $file], ['Accept' => 'application/json']);
}

function importSetup(): array
{
    $owner = createAdminUser();
    $other = BudgetCategory::factory()->create(['user_id' => $owner->id, 'name' => 'Other']);
    $food = BudgetCategory::factory()->create(['user_id' => $owner->id, 'name' => 'Food']);

    return [$owner, $other, $food];
}

test('negative amounts are spending and credits are skipped', function () {
    [$owner, $other] = importSetup();
    $csv = "Date,Description,Amount\n03/01/2026,KROGER #12,-54.20\n03/02/2026,PAYCHECK,2000.00\n03/03/2026,\"COFFEE, INC\",-4.50\n";

    importCsv($csv, $owner)->assertOk()->assertJson(['imported' => 2, 'skipped' => 1, 'duplicates' => 0]);

    expect(Expense::where('user_id', $owner->id)->count())->toBe(2)
        ->and(Expense::where('name', 'COFFEE, INC')->first()->amount)->toEqual('4.50')
        ->and(Expense::first()->budget_category_id)->toBe($other->id)
        ->and(Expense::where('name', 'KROGER #12')->first()->date->toDateString())->toBe('2026-03-01');
});

test('files with only positive amounts treat them all as spending, and categories are matched by name', function () {
    [$owner, , $food] = importSetup();
    $csv = "Transaction Date;Merchant;Debit;Category\n2026-03-01;Market;12,50;food\n2026-03-02;Gas;40.00;Unknown\n";

    importCsv($csv, $owner)->assertOk()->assertJson(['imported' => 2]);

    $market = Expense::where('name', 'Market')->first();
    expect($market->budget_category_id)->toBe($food->id)   // matched case-insensitively
        ->and((float) $market->amount)->toBe(12.5)           // decimal comma, not 1250
        ->and((float) Expense::where('name', 'Gas')->first()->amount)->toBe(40.0);
});

test('thousands separators are not mistaken for decimal commas', function () {
    [$owner] = importSetup();

    importCsv("date,description,amount\n2026-03-01,Rent,\"-1,250.00\"\n2026-03-02,Car,\"-1,250\"\n2026-03-03,Fee,(15.25)\n", $owner)
        ->assertOk()->assertJson(['imported' => 3]);

    expect(Expense::pluck('amount', 'name')->map(fn ($a) => (float) $a)->all())
        ->toBe(['Rent' => 1250.0, 'Car' => 1250.0, 'Fee' => 15.25]);
});

test('re-importing the same file does not duplicate expenses', function () {
    [$owner] = importSetup();
    $csv = "date,description,amount\n2026-03-01,Store,-10.00\n";

    importCsv($csv, $owner)->assertJson(['imported' => 1]);
    importCsv($csv, $owner)->assertJson(['imported' => 0, 'duplicates' => 1]);

    expect(Expense::count())->toBe(1);
});

test('unreadable files and rows are reported without crashing', function () {
    [$owner] = importSetup();

    importCsv("foo,bar\n1,2\n", $owner)->assertStatus(422);
    importCsv("date,description,amount\nnot-a-date,X,-5\n2026-03-01,Y,-6\n", $owner)
        ->assertOk()->assertJson(['imported' => 1])->assertJsonPath('errors.0', 'Line 2 could not be read.');
});

test('importing is limited to people who can add budget entries and lands in their own family', function () {
    [$owner] = importSetup();
    $child = createUserWithRole('child');
    $csv = "date,description,amount\n2026-03-01,Store,-10.00\n";

    importCsv($csv, $child)->assertForbidden();

    $stranger = createAdminUser();
    BudgetCategory::factory()->create(['user_id' => $stranger->id, 'name' => 'Other']);
    importCsv($csv, $stranger)->assertOk();
    expect(Expense::where('user_id', $owner->id)->count())->toBe(0);
});
