<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

final class Validator
{
    /**
     * @param array $record
     * @param array $requiredSourceFields
     * @return void
     */
    public function validate(array $record, array $requiredSourceFields): void
    {
        foreach ($requiredSourceFields as $field) {
            $value = $record[$field] ?? null;

            if ($value === null || $value === '') {
                throw new \RuntimeException(
                    sprintf('Required field "%s" is missing or empty in record', $field),
                    1718450201
                );
            }
        }
    }
}
