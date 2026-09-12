<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/TimeCalculator.php';

$schedule = [
    'work_start' => '08:00',
    'break_start' => '12:00',
    'break_end' => '13:00',
    'work_end' => '17:00',
    'night_start' => '22:00',
    'night_end' => '05:00',
];

$result = TimeCalculator::analyseDay([
    '2026-09-10 08:00:00', '2026-09-10 12:00:00',
    '2026-09-10 13:00:00', '2026-09-10 17:00:00',
], $schedule, 10);

assertSame(480, $result['worked_minutes'], 'horas trabalhadas');
assertSame(480, $result['expected_minutes'], 'horas previstas');
assertSame(0, $result['overtime_minutes'], 'horas extras');
assertSame([], $result['issues'], 'ocorrências');

$missing = TimeCalculator::analyseDay(['2026-09-10 08:00:00'], $schedule, 10);
if (!in_array('missing_punch', $missing['issues'], true)) fail('marcação ímpar não identificada');

$night = TimeCalculator::analyseDay(['2026-09-10 22:00:00','2026-09-11 05:00:00'], $schedule, 10);
assertSame(420, $night['night_minutes'], 'período noturno');

echo "Motor de cálculo validado.\n";

function assertSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) fail("$label: esperado " . json_encode($expected) . ', recebido ' . json_encode($actual));
}

function fail(string $message): never
{
    fwrite(STDERR, "Falha: $message\n"); exit(1);
}
