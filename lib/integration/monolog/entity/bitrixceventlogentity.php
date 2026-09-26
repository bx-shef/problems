<?php declare(strict_types=1);

namespace Shef\Problems\Integration\Monolog\Entity;

use Monolog\Level;

class BitrixCEventLogEntity
{
	private const EmptyValue = 'UNKNOWN';
	
	private int $itemId = 0;
	private string $moduleId = '';
	private string $severity = '';
	private array $description = [];
	
	// region Get / Set /////
	
	/**
	 * @param int $itemId
	 * @return $this
	 */
	public function setItemId(int $itemId): self
	{
		$this->itemId = $itemId;
		return $this;
	}
	
	/**
	 * @return int
	 */
	public function getItemId(): int
	{
		return $this->itemId ?? -1;
	}
	
	/**
	 * @param string $moduleId
	 * @return $this
	 */
	public function setModuleId(string $moduleId): self
	{
		$this->moduleId = $moduleId;
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getModuleId(): string
	{
		return $this->moduleId ?? self::EmptyValue;
	}
	
	/**
	 * Уровень Monolog -> SEVERITY журнала событий.
	 *
	 * Журнал знает пять значений: SECURITY, ERROR, WARNING, INFO, DEBUG, — а
	 * всё остальное CEventLog::Add() записывает как UNKNOWN. Раньше сюда шло
	 * имя уровня как есть, и CRITICAL, ALERT, EMERGENCY — самые важные
	 * записи — ложились в журнал «неизвестными» и не находились фильтром по
	 * важности. Исходный уровень не теряется: он стоит в заголовке описания,
	 * см. BitrixCEventLogFormatter::format().
	 *
	 * @param Level $level
	 * @return $this
	 *
	 * @see \CEventLog::Add()
	 */
	public function setSeverity(Level $level): self
	{
		$this->severity = static::mapSeverity($level);
		return $this;
	}
	
	public static function mapSeverity(Level $level): string
	{
		return match($level)
		{
			Level::Debug => 'DEBUG',
			Level::Info, Level::Notice => 'INFO',
			Level::Warning => 'WARNING',
			Level::Error, Level::Critical, Level::Alert, Level::Emergency => 'ERROR',
		};
	}
	
	/**
	 * @return string
	 */
	public function getSeverity(): string
	{
		return $this->severity ?? self::EmptyValue;
	}
	
	/**
	 * @param array $description
	 * @return $this
	 */
	public function setDescription(array $description): self
	{
		$this->description = $description;
		return $this;
	}
	
	/**
	 * @param string $key
	 * @param string|array $value
	 * @return $this
	 */
	public function addDescription(string $key, string|array $value): self
	{
		if(is_array($value))
		{
			$this->description[] = sprintf('%s <<', (string)$key);
			foreach($value as $keyRow => $valueRow)
			{
				$this->description[] = sprintf('{%s} = {%s}', (string)$keyRow, (string)$valueRow);
			}
			$this->description[] = sprintf('>>');
		}
		else
		{
			$this->description[] = sprintf('%s << {%s} >>', (string)$key, $value);
		}
		
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getDescription(): array
	{
		return $this->description;
	}
	
	/**
	 * @return string
	 */
	public function getFormattedDescription(): string
	{
		if(empty($this->description))
		{
			return '';
		}
		
		// return serialize($this->description); ////
		return join(PHP_EOL, $this->description);
	}
	
	
}