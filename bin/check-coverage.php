<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}

if (3 !== $argc) {
    fail('Usage: php bin/check-coverage.php <clover.xml> <minimum percent>');
}

[, $file, $minimum] = $argv;

if (!is_numeric($minimum) || (float) $minimum < 0 || (float) $minimum > 100) {
    fail(sprintf('Minimum must be a number between 0 and 100, got "%s".', $minimum));
}

if (!is_file($file) || !is_readable($file)) {
    fail(sprintf('Clover report "%s" not found.', $file));
}

$document = new DOMDocument();
if (!@$document->load($file, LIBXML_NONET)) {
    fail(sprintf('Clover report "%s" is not valid XML.', $file));
}

$metrics = (new DOMXPath($document))->query('/coverage/project/metrics')?->item(0);
if (!$metrics instanceof DOMElement) {
    fail(sprintf('Clover report "%s" has no project metrics.', $file));
}

$statements = (int) $metrics->getAttribute('statements');
$covered = (int) $metrics->getAttribute('coveredstatements');

if ($statements <= 0) {
    fail('Clover report contains no statements.');
}

$percent = $covered / $statements * 100;
$line = sprintf('Line coverage %.2f%% (%d/%d), minimum %.2f%%.', $percent, $covered, $statements, (float) $minimum);

if ($percent < (float) $minimum) {
    fail($line);
}

echo $line.PHP_EOL;
