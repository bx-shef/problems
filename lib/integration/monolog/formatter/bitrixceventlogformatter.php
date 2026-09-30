<?php

declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Monolog\Utils;
use Shef\Problems\Integration\Monolog\Entity\BitrixCEventLogEntity;

use function count;

/**
 * Record formatter for event log of Bitrix Control Panel.
 *
 * Context of record will also be written to CEventLog of Bitrix
 *
 * You can use keys {`int itemId`, `string moduleId`}
 * ```php
 * $logger->error('message', [
 *      'itemId' => 21,
 *      'moduleId' => 'shef.demo',
 * ]);
 * ```
 * @see \CEventMain::GetEventInfo()
 */
class BitrixCEventLogFormatter extends NormalizerFormatter
{
    public const SIMPLE_DATE = "Y-m-d H:i:s";
    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record): BitrixCEventLogEntity
    {
        $output = new BitrixCEventLogEntity();
        $output->setSeverity($record->level);

        $title = sprintf(
            '[%s->%s]',
            $record->channel,
            $record->level->getName(),
        );

        $message = sprintf(
            '%s',
            $record->message,
        );
        $output->addDescription($title, $message);

        // $output->addDescription('Level', ); ///
        // $output->addDescription('Time', $this->formatDate($record->datetime)); ////

        if (count($record->context) > 0) {
            $list = [];
            foreach ($record->context as $key => $value) {
                if (
                    $key === 'itemId'
                    && is_integer($value)
                ) {
                    $output->setItemId($value);
                } elseif (
                    $key === 'moduleId'
                    && is_string($value)
                ) {
                    $output->setModuleId($value);
                } else {
                    $list[(string)$key] = $this->convertToString($value);
                }
            }
            if (!empty($list)) {
                $output->addDescription('Context', $list);
            }
            unset($list);
        }

        if (count($record->extra) > 0) {
            $list = [];
            foreach ($record->extra as $key => $value) {
                if (
                    $key === 'moduleId'
                    && is_string($value)
                ) {
                    $output->setModuleId($value);
                } else {
                    $list[(string)$key] = $this->convertToString($value);
                }
            }

            if (!empty($list)) {
                $output->addDescription('Extra', $list);
            }
            unset($list);
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): BitrixCEventLogEntity
    {
        $output = new BitrixCEventLogEntity();
        $isInit = false;
        foreach ($records as $record) {
            $response = $this->format($record);
            if (!$isInit) {
                $output->setModuleId($response->getModuleId());
                $output->setSeverity($record->level);

                $isInit = true;
            }

            $output->addDescription($record->channel.'|'.$record->message, $response->getFormattedDescription());

            unset($response);
        }

        return $output;
    }

    protected function convertToString($data): string
    {
        if (null === $data || is_scalar($data)) {
            return (string)$data;
        }

        $data = $this->normalize($data);

        return Utils::jsonEncode($data, JSON_PRETTY_PRINT | Utils::DEFAULT_JSON_FLAGS, true);
    }
}
