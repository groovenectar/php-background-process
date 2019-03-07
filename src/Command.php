<?php

namespace Pbp;

use \Exception;

class Command
{
	protected $args;
	protected $cmd;

	public function __construct($cmd, $args = [])
	{
		$cmdPathParts = explode(DIRECTORY_SEPARATOR, ltrim($cmd, DIRECTORY_SEPARATOR));

		if ($cmdPathParts !== array_filter($cmdPathParts, [__CLASS__, 'isShellSafe'])) {
			throw new \Exception('Invalid command specified');
		}

		$this->cmd = $cmd;
		$this->args = $args;
	}

	public function __toString()
	{
		return implode(' ', array_map([$this, 'escapeShellArg'], array_merge([$this->cmd], $this->args ?: [])));
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function exec() {
		exec($this, $output, $return);

		if ($return !== 0) {
			throw new Exception('Command ' . self::escapeShellArg($this->cmd) . ' failed', (int) $return);
		}

		return implode("\n", $output);
	}

	/**
	 * Like exec(), but uses passthru() to display output as it happens
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function passthru() {
		passthru($this, $return);

		if ($return !== 0) {
			throw new Exception('Command ' . self::escapeShellArg($this->cmd) . ' failed', (int) $return);
		}

		return true;
	}

	public function exists() {
		if (strpos($this->cmd, DIRECTORY_SEPARATOR) !== false) {
			return file_exists($this->cmd) && is_executable($this->cmd);
		}

		$which = new self('which', [$this->cmd]);
		try {
			return $which->exec();
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * From Symfony: https://github.com/symfony/process/blob/e9f208633ac7ef167801cf4da916e07a6149fa33/Process.php
	 * Handles special characters without specifying locale
	 *
	 * Escapes a string to be used as a shell argument.
	 */

	public static function escapeShellArg(?string $argument): string
	{
		if ('' === $argument || null === $argument) {
			return '""';
		}
		if ('\\' !== \DIRECTORY_SEPARATOR) {
			return "'" . str_replace("'", "'\\''", $argument) . "'";
		}
		if (false !== strpos($argument, "\0")) {
			$argument = str_replace("\0", '?', $argument);
		}
		if (!preg_match('/[\/()%!^"<>&|\s]/', $argument)) {
			return $argument;
		}
		$argument = preg_replace('/(\\\\+)$/', '$1$1', $argument);

		return '"' . str_replace(['"', '^', '%', '!', "\n"], ['""', '"^^"', '"^%"', '"^!"', '!LF!'], $argument) . '"';
	}

	/**
	 * This is PHPMailer's version: https://github.com/PHPMailer/PHPMailer/blob/76c6b4c0a7f265e5df9e20a46d7c84b86184b2b7/src/PHPMailer.php
	 *
	 * Prevent attacks similar to CVE-2016-10033, CVE-2016-10045, and CVE-2016-10074
	 * by disallowing potentially unsafe shell characters.
	 *
	 * @param   string $string the string to be tested for shell safety
	 * @see     https://gist.github.com/Zenexer/40d02da5e07f151adeaeeaa11af9ab36
	 * @author  Paul Buonopane <paul@namepros.com>
	 * @license Public doman per CC0 1.0.  Attribution appreciated but not required.
	 */
	public function isShellSafe($string)
	{
		$string = strval($string);
		$length = strlen($string);

		// If you need to allow empty strings, you can remove this, but be sure you
		// understand the security implications of doing so.
		if (!$length) {
			return false;
		}

		// Method 1
		// Note: Results may be indeterminate with a stateful encodings, e.g. EUC
		for ($i = 0; $i < $length; $i++) {
			$c = $string[$i];
			if (!ctype_alnum($c) && strpos('@_-.', $c) === false) {
				return false;
			}
		}
		//return true;

		// Method 2
		// Note: Assumes UTF-8 encoding.  Conversion may be necessary.
		return (bool)preg_match('/\A[\pL\pN._@-]*\z/ui', $string);
	}
}
