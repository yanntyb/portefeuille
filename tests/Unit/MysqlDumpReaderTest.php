<?php

use Database\Seeders\Support\MysqlDumpReader;

function writeDump(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'dump').'.sql';
    file_put_contents($path, $contents);

    return $path;
}

it('reads every tuple of an insert statement', function () {
    $path = writeDump(<<<'SQL'
        INSERT INTO `wallets` VALUES (1,1,'PEA','2026-03-16 17:47:26','2026-03-16 17:47:26'),(2,1,'CTO','2026-03-16 17:47:26','2026-03-16 17:47:26');
        SQL);

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('wallets'));

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['1', '1', 'PEA', '2026-03-16 17:47:26', '2026-03-16 17:47:26'])
        ->and($rows[1][2])->toBe('CTO');
});

it('turns NULL into null and keeps numbers as strings', function () {
    $path = writeDump(<<<'SQL'
        INSERT INTO `transactions` VALUES (17,1,2,'2026-01-01','buy',3,NULL,1.0000,155.2000,50.16,NULL,NULL,'2026-02-21 14:02:35','2026-02-24 21:46:37');
        SQL);

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('transactions'));

    expect($rows[0][5])->toBe('3')
        ->and($rows[0][6])->toBeNull()
        ->and($rows[0][7])->toBe('1.0000')
        ->and($rows[0][10])->toBeNull();
});

it('unescapes quotes, backslashes and control sequences inside strings', function () {
    $path = writeDump(
        'INSERT INTO `feedback` VALUES '
        ."(1,4,'Export CSV','J\\'aimerais un \\\\ et un saut\\nde ligne',NULL,NULL);\n"
    );

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('feedback'));

    expect($rows[0][3])->toBe("J'aimerais un \\ et un saut\nde ligne");
});

it('handles a doubled quote as an escaped quote', function () {
    $path = writeDump(
        "INSERT INTO `feedback` VALUES (1,4,'L''export','ok',NULL,NULL);\n"
    );

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('feedback'));

    expect($rows[0][2])->toBe("L'export");
});

it('does not confuse a parenthesis inside a string with a tuple boundary', function () {
    $path = writeDump(
        "INSERT INTO `assets` VALUES (1,'FR0011871110','PUST.PA','Amundi (PEA) Nasdaq-100, Acc',NULL,NULL);\n"
    );

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('assets'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0][3])->toBe('Amundi (PEA) Nasdaq-100, Acc');
});

it('reads the tuples of every insert statement of the same table', function () {
    $path = writeDump(<<<'SQL'
        INSERT INTO `security_prices` VALUES (1,1,'2021-06-04',1.0,2.0,3.0,4.0,100,NULL,NULL);
        INSERT INTO `security_prices` VALUES (2,1,'2021-06-07',1.0,2.0,3.0,4.0,200,NULL,NULL);
        SQL);

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('security_prices'));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][2])->toBe('2021-06-07');
});

it('ignores the other tables and the DDL', function () {
    $path = writeDump(<<<'SQL'
        CREATE TABLE `wallets` (`id` bigint unsigned NOT NULL);
        INSERT INTO `users` VALUES (1,'Yann','yann@example.com');
        INSERT INTO `wallets` VALUES (1,1,'PEA',NULL,NULL);
        SQL);

    $rows = iterator_to_array((new MysqlDumpReader($path))->rows('wallets'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0][2])->toBe('PEA');
});

it('yields nothing when the table is absent from the dump', function () {
    $path = writeDump("INSERT INTO `users` VALUES (1,'Yann');\n");

    expect(iterator_to_array((new MysqlDumpReader($path))->rows('wallets')))->toBe([]);
});

it('yields nothing when the file does not exist', function () {
    $reader = new MysqlDumpReader(sys_get_temp_dir().'/absent-dump.sql');

    expect(iterator_to_array($reader->rows('wallets')))->toBe([]);
});
