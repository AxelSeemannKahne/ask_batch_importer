<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

final class ProductDataMapper
{
    private array $mapping = [];

    /**
     * @param array $mapping
     * @return void
     */
    public function setMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }

    /**
     * @param array $record
     * @return array
     */
    public function map(array $record): array
    {
        $result = [];

        foreach ($this->mapping as $targetField => $fieldConfig) {
            $result[$targetField] = $this->resolveValue($record, $fieldConfig);
        }

        return $result;
    }

    /**
     * @param array $record
     * @param array $fieldConfig
     * @return mixed
     */
    private function resolveValue(array $record, array $fieldConfig): mixed
    {
        $type = $fieldConfig['type'] ?? 'string';

        if ($type === 'static') {
            return $fieldConfig['value'];
        }

        $source = $fieldConfig['source'];
        $raw = $record[$source] ?? null;

        return match ($type) {
            'string' => (string)($raw ?? ''),
            'int'    => (int)($raw ?? 0),
            'float'  => (float)($raw ?? 0),
            'bool'   => (int)(bool)($raw ?? false),
            default  => $raw,
        };
    }
}
