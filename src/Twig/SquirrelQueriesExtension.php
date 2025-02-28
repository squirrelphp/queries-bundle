<?php

namespace Squirrel\QueriesBundle\Twig;

use SqlFormatter;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * This class contains the needed functions in order to do the query highlighting
 */
final class SquirrelQueriesExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('squirrel_pretty_query', [$this, 'formatQuery'], ['is_safe' => ['html']]),
            new TwigFilter('squirrel_replace_query_parameters', [$this, 'replaceQueryParameters']),
        ];
    }

    /**
     * Escape parameters of a SQL query
     * DON'T USE THIS FUNCTION OUTSIDE ITS INTENDED SCOPE
     *
     * @internal
     */
    public static function escapeFunction(mixed $parameter): string
    {
        $result = $parameter;

        switch (true) {
            // Check if result is non-unicode string using PCRE_UTF8 modifier
            case \is_string($result) && !\preg_match('//u', $result):
                $result = '0x' . \strtoupper(\bin2hex($result));
                break;

            case \is_string($result):
                $result = "'" . \addslashes($result) . "'";
                break;

            case \is_array($result):
                foreach ($result as &$value) {
                    $value = self::escapeFunction($value);
                }

                $result = \implode(', ', $result);
                break;

            case \is_object($result):
                /**
                 * @psalm-suppress InvalidCast
                 */
                $result = \addslashes((string) $result);
                break;

            case $result === null:
                $result = 'NULL';
                break;

            case \is_bool($result):
                $result = $result ? '1' : '0';
                break;
        }

        return $result;
    }

    /**
     * Return a query with the parameters replaced
     */
    public function replaceQueryParameters(string $query, array|Data $parameters): string
    {
        if ($parameters instanceof Data) {
            $parameters = $parameters->getValue(true);

            if (!\is_array($parameters)) {
                throw new \LogicException('Parameters is not an array, it is set to: ' . \print_r($parameters, true));
            }
        }

        $i = 0;

        if (!\array_key_exists(0, $parameters) && \array_key_exists(1, $parameters)) {
            $i = 1;
        }

        return \preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            /**
             * @return mixed
             */
            static function (array $matches) use ($parameters, &$i) {
                /**
                 * @var string|bool $key
                 */
                $key = \substr($matches[0], 1);

                if (
                    !\array_key_exists($i, $parameters)
                    && (
                        (\is_bool($key) && $key === false)
                        || (\is_string($key) && !\array_key_exists($key, $parameters))
                    )
                ) {
                    return $matches[0];
                }

                /**
                 * @var string $key
                 */
                $value  = \array_key_exists($i, $parameters) ? $parameters[$i] : $parameters[$key];
                $result = self::escapeFunction($value);
                $i++;

                return $result;
            },
            $query,
        ) ?? '';
    }

    /**
     * Formats and/or highlights the given SQL statement.
     */
    public function formatQuery(string $sql, bool $highlightOnly = false): string
    {
        SqlFormatter::$pre_attributes            = 'class="highlight highlight-sql"';
        SqlFormatter::$quote_attributes          = 'class="string"';
        SqlFormatter::$backtick_quote_attributes = 'class="string"';
        SqlFormatter::$reserved_attributes       = 'class="keyword"';
        SqlFormatter::$boundary_attributes       = 'class="symbol"';
        SqlFormatter::$number_attributes         = 'class="number"';
        SqlFormatter::$word_attributes           = 'class="word"';
        SqlFormatter::$error_attributes          = 'class="error"';
        SqlFormatter::$comment_attributes        = 'class="comment"';
        SqlFormatter::$variable_attributes       = 'class="variable"';

        if ($highlightOnly) {
            $html = SqlFormatter::highlight($sql);
            $html = \preg_replace('/<pre class=".*">([^"]*+)<\/pre>/Us', '\1', $html);
        } else {
            $html = SqlFormatter::format($sql);
            $html = \preg_replace('/<pre class="(.*)">([^"]*+)<\/pre>/Us', '<div class="\1"><pre>\2</pre></div>', $html);
        }

        return $html ?? '';
    }
}
