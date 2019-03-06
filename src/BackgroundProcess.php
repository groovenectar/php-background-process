<?php

namespace Pbp;

class BackgroundProcess
{
	protected $id;
	protected $pid;

	public function __construct($id)
	{
		$this->id = $id;
	}

	public function isRunning()
	{
		$pid = $this->getPid();
		exec("test -e /proc/$pid", $out, $ret);
		return !$ret;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getPid()
	{
		if (!$this->pid) {
			$this->pid = file_get_contents(
				self::getFilePath($this->id, 'pid')
			);
		}
		return $this->pid;
	}

	public function getOutput()
	{
		$filePath = self::getFilePath($this->id, 'out');
		return file_exists($filePath) ? file_get_contents(
			self::getFilePath($this->id, 'out')
		) : null;
	}

	public function grepOutput($regex)
	{
		$output = $this->getOutput();

		if (!$output) {
			return [];
		}

		preg_match_all($regex, $output, $matches);
		return $matches;
	}

	public function getProgress($callback) {
		return $callback($this);
	}

	public function cleanUp()
	{
		$pid = self::getFilePath($this->id, 'pid');
		$out = self::getFilePath($this->id, 'out');
		if (file_exists($pid)) {
			unlink($pid);
			unlink($out);
			return true;
		}
		return false;
	}

	private static function getFilePath($id, $ext)
	{
		return '/tmp/bg-process-' . $id . '.' . $ext;
	}

	private static function _generateUniqueId() {
		return round(microtime(true) * 1000) . random_int(100, 999);
	}

	public static function exec($cmd)
	{
		$id = self::_generateUniqueId();

		$out = self::getFilePath($id, 'out');
		$pid = self::getFilePath($id, 'pid');

		$execStr = 'nohup ' . $cmd . ' > ' . $out . ' 2>&1 & echo $!';
		exec($execStr, $output, $ret);

		if ($ret) {
			throw new Exception('Background process failed', (int) $ret);
		}

		file_put_contents($pid, $output[0]);

		return new self($id);
	}
}
