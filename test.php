<?php

namespace Pbp;

require __DIR__ . '/vendor/autoload.php';

function expect($cond, $msg = '') {
	if (!$cond) {
		echo "✖️  $msg\n\n";
		throw new Exception($msg);
	} else {
		echo "✔️  $msg\n";
	}
}

$command = new Command('sh', ['-c', 'sleep 1; echo -n done']);
$bp = BackgroundProcess::exec($command);

expect($bp->getId(), 'it has an ID: ' . $bp->getId());
expect($bp->getPid(), 'it has a PID: ' . $bp->getPid());
expect($bp->isRunning(), 'running right away');

$bp2 = new BackgroundProcess($bp->getId());
expect($bp2->getPid() === $bp->getPid(), 'same PID');
expect($bp2->isRunning(), 'still running');

sleep(1);

expect(!$bp2->isRunning(), 'stopped running after 1 second');
expect(!$bp->isRunning(), 'stopped running after 1 second');
expect($bp->getOutput() === "done", 'gets output');
expect($bp->cleanUp(), 'clean-up');
expect(!$bp2->cleanUp(), 'already cleaned-up');
