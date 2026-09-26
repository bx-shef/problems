<?php declare(strict_types=1);

namespace Shef\Problems\Main;

/**
 * Файлы в каталоге логов: список, проверка имени, хвост файла.
 *
 * Каталог логов лежит вне корня сайта (Constants::getLogDir()), поэтому ни
 * прямая ссылка, ни файловый менеджер Битрикса до него не дотянутся — смотреть
 * логи можно только страницей модуля /bitrix/admin/shef_problems_logs.php. Имя
 * файла приходит в неё параметром запроса, то есть от кого угодно, и этот
 * класс — единственное место, где оно превращается в путь.
 *
 * Имя проверяется дважды:
 *
 * * по шаблону — только буквы, цифры, «_» и «-», расширение .log и, для
 *   файлов после logrotate, номер: log.log, sh_problems_sync.log.1. Ни «/»,
 *   ни «..» через шаблон не пройдут;
 * * после разрешения пути — файл обязан лежать внутри каталога логов.
 *   realpath() раскрывает символические ссылки, и ссылка с допустимым именем,
 *   ведущая наружу, здесь и отсекается. Разделитель в конце префикса
 *   обязателен: иначе каталогу sh_log подошёл бы сосед sh_log-old.
 *
 * Сжатые logrotate файлы (.gz) не показываются: читать их построчно нечем.
 */
class LogFiles
{
	public const NAME_PATTERN = '/^[A-Za-z0-9_-]+\.log(\.[0-9]+)?$/';

	/**
	 * Сколько байт с конца файла показывать по умолчанию.
	 */
	public const TAIL_BYTES = 262144;

	public function __construct(
		private readonly string $dir
	)
	{
	}

	public static function create(): static
	{
		return new static(Constants::getLogDir());
	}

	public function getDir(): string
	{
		return $this->dir;
	}

	public static function isValidName(string $name): bool
	{
		return 1 === preg_match(static::NAME_PATTERN, $name);
	}

	/**
	 * Файлы логов, по имени.
	 *
	 * @return list<array{name: string, size: int, modified: int}>
	 */
	public function getList(): array
	{
		if(!is_dir($this->dir))
		{
			return [];
		}

		$list = [];
		foreach(scandir($this->dir) ?: [] as $name)
		{
			if(!static::isValidName($name))
			{
				continue;
			}

			$path = $this->resolve($name);
			if(null === $path)
			{
				continue;
			}

			$list[] = [
				'name' => $name,
				'size' => (int)filesize($path),
				'modified' => (int)filemtime($path),
			];
		}

		usort($list, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

		return $list;
	}

	/**
	 * Имя файла -> настоящий путь внутри каталога логов, либо null.
	 */
	public function resolve(string $name): ?string
	{
		if(!static::isValidName($name))
		{
			return null;
		}

		$dir = realpath($this->dir);
		$path = realpath($this->dir.'/'.$name);

		if(false === $dir || false === $path || !is_file($path))
		{
			return null;
		}

		if(!str_starts_with($path, rtrim($dir, '/').'/'))
		{
			return null;
		}

		return $path;
	}

	/**
	 * Конец файла: не больше $bytes байт, с целой первой строкой.
	 *
	 * Логи бывают по сотне мегабайт, а смотрят их в браузере — целиком читать
	 * нельзя. Если файл обрезан, первая неполная строка отбрасывается.
	 *
	 * @return array{content: string, truncated: bool, size: int}
	 */
	public function tail(string $path, int $bytes = self::TAIL_BYTES): array
	{
		$size = (int)filesize($path);
		$truncated = $size > $bytes;

		$handle = fopen($path, 'rb');
		if(false === $handle)
		{
			return ['content' => '', 'truncated' => false, 'size' => $size];
		}

		if($truncated)
		{
			fseek($handle, -$bytes, SEEK_END);
		}

		$content = (string)stream_get_contents($handle);
		fclose($handle);

		if($truncated)
		{
			$lineEnd = strpos($content, "\n");
			$content = false === $lineEnd ? '' : substr($content, $lineEnd + 1);
		}

		return ['content' => $content, 'truncated' => $truncated, 'size' => $size];
	}
}
