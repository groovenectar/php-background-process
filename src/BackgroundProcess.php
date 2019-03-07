<?php

namespace Pbp;

use \Exception;

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
		try {
			$pid = $this->getPid();
			$command = new Command(
				'test',
				[
					'-e',
					"/proc/$pid"
				]
			);
			return $command->exec();
		} catch (Exception $e) {
			return false;
		}
	}

	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return false|string|null
	 * @throws Exception
	 */
	public function getPid()
	{
		if (!$this->pid) {
			$pid = self::getFileContents($this->id, 'pid');

			if (is_null($pid)) {
				throw new Exception('Could not find process');
			}

			$this->pid = $pid;
		}

		return $this->pid;
	}

	public function getOutput()
	{
		return self::getFileContents($this->id, 'out');
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

	public function tailOutput($lines = 1)
	{
		return $this->_headOrTailOutput('tail', $lines);
	}

	public function headOutput($lines = 1)
	{
		return $this->_headOrTailOutput('head', $lines);
	}

	private function _headOrTailOutput($operation = 'tail', $lines = 1)
	{
		$outFilePath = self::getFilePath($this->id, 'out');

		if (!file_exists($outFilePath)) {
			return null;
		}

		$cmd = new Command(
			$operation,
			[
				'-n',
				intval($lines),
				$outFilePath
			]
		);

		return $cmd->exec();
	}

	public function callback($callback) {
		return $callback($this);
	}

	public function cleanUp()
	{
		$pidFilePath = self::getFilePath($this->id, 'pid');
		$outFilePath = self::getFilePath($this->id, 'out');

		if (file_exists($pidFilePath)) {
			unlink($pidFilePath);
			unlink($outFilePath);
			return true;
		}

		return false;
	}

	private static function getFileContents($id, $ext) {
		$filePath = self::getFilePath($id, $ext);
		return file_exists($filePath) ? file_get_contents($filePath) : null;
	}

	private static function getFilePath($id, $ext)
	{
		return '/tmp/bg-process-' . $id . '.' . $ext;
	}

	private static function _generateUniqueId() {
		return round(microtime(true) * 1000) . random_int(100, 999);
	}

	/**
	 * @param Command $cmd
	 * @return BackgroundProcess
	 * @throws Exception
	 */
	public static function exec(Command $cmd)
	{
		$id = self::_generateUniqueId();

		$outFilePath = self::getFilePath($id, 'out');
		$pidFilePath = self::getFilePath($id, 'pid');

		$execStr = 'nohup ' . $cmd->__toString() . ' > ' . Command::escapeShellArg($outFilePath) . ' 2>&1 & echo $!';
		exec($execStr, $output, $return);

		if ($return) {
			throw new Exception('Background process failed', (int) $return);
		}

		file_put_contents($pidFilePath, $output[0]);

		return new self($id);
	}
}
