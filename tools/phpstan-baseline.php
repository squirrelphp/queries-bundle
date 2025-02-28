<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:change\\(\\) should return int but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:delete\\(\\) should return int but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:fetch\\(\\) should return array\\<string, mixed\\>\\|null but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:fetchAll\\(\\) should return array\\<int, array\\<string, mixed\\>\\> but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:fetchAllAndFlatten\\(\\) should return array\\<bool\\|float\\|int\\|string\\|null\\> but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:fetchOne\\(\\) should return array\\<string, mixed\\>\\|null but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:insert\\(\\) should return string\\|null but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:select\\(\\) should return Squirrel\\\\Queries\\\\DBSelectQueryInterface but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Examples\\\\SQLLogTemporaryFailuresListener\\:\\:update\\(\\) should return int but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../examples/SQLLogTemporaryFailuresListener.php',
];
$ignoreErrors[] = [
	// identifier: argument.type
	'message' => '#^Parameter \\#1 \\$queries of method Squirrel\\\\QueriesBundle\\\\DataCollector\\\\SquirrelDataCollector\\:\\:sanitizeQueries\\(\\) expects array\\<int, array\\{query\\: string, values\\: array\\<bool\\|float\\|int\\|Squirrel\\\\Connection\\\\LargeObject\\|string\\>, time\\: int\\}\\>, array\\<int, array\\{query\\: string, values\\: array\\<bool\\|float\\|int\\|Squirrel\\\\Connection\\\\LargeObject\\|string\\|null\\>, time\\: int\\}\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../src/DataCollector/SquirrelDataCollector.php',
];
$ignoreErrors[] = [
	// identifier: assign.propertyType
	'message' => '#^Property Squirrel\\\\QueriesBundle\\\\DataCollector\\\\SquirrelDataCollector\\:\\:\\$groupedQueries \\(array\\<string, array\\<string, array\\{sql\\: string, executionMS\\: float\\|int, executionPercent\\: float, count\\: int, index\\: int, values\\: array\\<bool\\|float\\|int\\|string\\|null\\>\\}\\>\\>\\|null\\) does not accept non\\-empty\\-array\\<array\\<int\\|string, mixed\\>\\>\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/../src/DataCollector/SquirrelDataCollector.php',
];
$ignoreErrors[] = [
	// identifier: method.notFound
	'message' => '#^Call to an undefined method Symfony\\\\Component\\\\Config\\\\Definition\\\\Builder\\\\NodeParentInterface\\:\\:scalarNode\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../src/DependencyInjection/Configuration.php',
];
$ignoreErrors[] = [
	// identifier: cast.string
	'message' => '#^Cannot cast object to string\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../src/Twig/SquirrelQueriesExtension.php',
];
$ignoreErrors[] = [
	// identifier: return.type
	'message' => '#^Method Squirrel\\\\QueriesBundle\\\\Twig\\\\SquirrelQueriesExtension\\:\\:escapeFunction\\(\\) should return string but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/../src/Twig/SquirrelQueriesExtension.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
