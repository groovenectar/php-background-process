<?php

namespace Pbp;

class Command
{
	protected $args;
	protected $cmd;

	public function __construct($cmd, $args)
	{
		$this->cmd = $cmd;

		if (!$this->_isShellSafe($this->cmd)) {
			throw new \Exception('Invalid command specified');
		}

		$this->args = $args;
	}

	public function __toString()
	{
		return implode(' ', array_map([$this, '_escapeShellArg'], array_merge([$this->cmd], $this->args ?: [])));
	}

	/**
	 * From Symfony: https://github.com/symfony/process/blob/e9f208633ac7ef167801cf4da916e07a6149fa33/Process.php
	 * Handles special characters without specifying locale
	 *
	 * Escapes a string to be used as a shell argument.
	 */

	private function _escapeShellArg(?string $argument): string
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
	 * @param   string  $string      the string to be tested for shell safety
	 * @see     https://gist.github.com/Zenexer/40d02da5e07f151adeaeeaa11af9ab36
	 * @author  Paul Buonopane <paul@namepros.com>
	 * @license Public doman per CC0 1.0.  Attribution appreciated but not required.
	 */
	private function _isShellSafe($string)
	{
		// Future-proof
		if (escapeshellcmd($string) !== $string
			or !in_array($this->_escapeShellArg($string), ["'$string'", "\"$string\""])
		) {
			return false;
		}

		$length = strlen($string);
		for ($i = 0; $i < $length; ++$i) {
			$c = $string[$i];
			// All other characters have a special meaning in at least one common shell, including = and +.
			// Full stop (.) has a special meaning in cmd.exe, but its impact should be negligible here.
			// Note that this does permit non-Latin alphanumeric characters based on the current locale.
			if (!ctype_alnum($c) && strpos('@_-.', $c) === false) {
				return false;
			}
		}
		return true;
	}
}
