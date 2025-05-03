<?php
// src/CSVReader.php

namespace App;

/**
 * Lê uma coluna específica de um arquivo CSV
 */
class CSVReader
{
    private string $tmpName;
    private string $columnName;

    /**
     * @param string $tmpName    Caminho temporário do arquivo CSV
     * @param string $columnName Nome da coluna a ser lida (padrão "DocEntry")
     */
    public function __construct(string $tmpName, string $columnName = 'DocEntry')
    {
        $this->tmpName    = $tmpName;
        $this->columnName = $columnName;
    }

    /**
     * Abre o CSV, procura o índice da coluna e retorna todos os valores como inteiros.
     *
     * @return int[] Array de DocEntries
     * @throws \RuntimeException Se o arquivo não abrir ou a coluna não existir
     */
    public function readColumn(): array
    {
        $results = [];

        if (($handle = fopen($this->tmpName, 'r')) === false) {
            throw new \RuntimeException("Não foi possível abrir o CSV em {$this->tmpName}.");
        }

        // Lê cabeçalho e encontra índice
        $header = fgetcsv($handle, 0, ",");
        $idx    = array_search($this->columnName, $header);
        if ($idx === false) {
            fclose($handle);
            throw new \RuntimeException("Coluna '{$this->columnName}' não encontrada no CSV.");
        }

        // Lê linhas subsequentes
        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $val = trim($row[$idx]);
            if ($val !== '') {
                $results[] = (int)$val;
            }
        }

        fclose($handle);
        return $results;
    }
}
