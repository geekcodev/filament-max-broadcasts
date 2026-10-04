<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Feature;

use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

/**
 * Порядок применения миграций задаётся именем файла: Laravel сортирует миграции
 * приложения и всех пакетов по имени и идёт по списку сверху вниз. Поэтому
 * внешний ключ допустим только на таблицу, чья миграция имеет меньшее имя, а
 * «полоса» в имени миграции — это позиция пакета в общем порядке, а не отдельный
 * именованный диапазон.
 *
 * На SQLite нарушение не воспроизводится: там таблица с внешним ключом на
 * несуществующую таблицу создаётся молча, ошибка всплывает только на данных. На
 * PostgreSQL и MySQL DDL падает сразу, то есть чистая установка невозможна.
 * Поэтому порядок проверяется по исходникам миграций, а не через попытку
 * migrate.
 *
 * Здесь проверяется контракт полосы пакета: собственные миграции лежат в
 * 0000_03, ссылки на таблицы других полос ведут строго назад, а ссылки внутри
 * пакета — в более раннюю миграцию пакета. Файлы соседних пакетов в vendor не
 * читаются: там установленная версия laravel-max-client, чьи имена миграций
 * меняются независимо от этого репозитория. Сквозную проверку по реальным
 * файлам, где виден весь стек, делает tests/Feature/MigrationOrderTest в
 * приложении.
 */
final class MigrationOrderTest extends TestCase
{
    private const BAND = '0000_03_';

    /**
     * Таблицы других полос, на которые ссылаются миграции пакета, и их полосы.
     * Таблицы самого пакета сюда не входят: их порядок проверяется по файлам.
     *
     * @var array<string, string>
     */
    private const REFERENCED_TABLES = [
        'users' => '0000_01',
    ];

    public function testMigrationsUsePackageBand(): void
    {
        $paths = $this->ownMigrationPaths();
        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $name = basename($path, '.php');

            $this->assertStringStartsWith(
                self::BAND,
                $name,
                sprintf('%s: миграция вне полосы %s, порядок применения может сломаться', $name, self::BAND),
            );
        }
    }

    /**
     * Внешний ключ ведёт либо в строго более раннюю полосу, либо в более раннюю
     * миграцию внутри своей полосы.
     */
    public function testForeignKeysTargetEarlierMigrations(): void
    {
        $creators = $this->creators();

        foreach ($this->references() as $migration => $targets) {
            foreach ($targets as $table) {
                if (isset(self::REFERENCED_TABLES[$table])) {
                    $this->assertLessThan(
                        $migration,
                        self::REFERENCED_TABLES[$table].'0',
                        sprintf(
                            'Миграция %s ссылается на %s из полосы %s — позже или в той же полосе',
                            $migration,
                            $table,
                            self::REFERENCED_TABLES[$table],
                        ),
                    );

                    continue;
                }

                $this->assertArrayHasKey(
                    $table,
                    $creators,
                    sprintf(
                        'Миграция %s ссылается на %s, полоса которой не объявлена: добавь таблицу в '
                        . 'MigrationOrderTest или в создающую миграцию пакета',
                        $migration,
                        $table,
                    ),
                );

                $this->assertLessThan(
                    $migration,
                    $creators[$table],
                    sprintf(
                        'Миграция %s ссылается на %s, но та создаётся позже (%s)',
                        $migration,
                        $table,
                        $creators[$table],
                    ),
                );
            }
        }
    }

    /**
     * Таблицы, создаваемые миграциями пакета: имя файла создателя => таблица.
     *
     * @return array<string, string>
     */
    private function creators(): array
    {
        $creators = [];

        foreach ($this->ownMigrationPaths() as $path) {
            preg_match_all("/Schema::create\('([a-z_]+)'/", (string) file_get_contents($path), $matches);

            foreach ($matches[1] as $table) {
                $creators[$table] = basename($path, '.php');
            }
        }

        return $creators;
    }

    /**
     * Таблицы, на которые ссылаются миграции пакета: имя файла => список.
     *
     * @return array<string, list<string>>
     */
    private function references(): array
    {
        $references = [];

        foreach ($this->ownMigrationPaths() as $path) {
            preg_match_all("/->(?:on|constrained)\('([a-z_]+)'\)/", (string) file_get_contents($path), $matches);

            $tables = array_values(array_unique($matches[1]));

            if ($tables !== []) {
                $references[basename($path, '.php')] = $tables;
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function ownMigrationPaths(): array
    {
        $directory = dirname(__DIR__, 2).'/database/migrations';

        $this->assertDirectoryExists($directory);

        return glob($directory.'/*.php') ?: [];
    }
}
