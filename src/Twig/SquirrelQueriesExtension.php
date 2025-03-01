<?php

namespace Squirrel\QueriesBundle\Twig;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Squirrel\Debug\Debug;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/*
 * This class contains the needed functions in order to do the query highlighting and showing the query with the values
 */
final class SquirrelQueriesExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('squirrel_prettify_sql', $this->prettifySql(...), ['is_safe' => ['html']]),
            new TwigFilter('squirrel_format_sql', $this->formatSql(...), ['is_safe' => ['html']]),
            new TwigFilter('squirrel_replace_query_parameters', $this->replaceQueryParameters(...)),
        ];
    }

    private function escapeValue(mixed $value): string
    {
        if (
            \is_string($value)
            && !\preg_match('//u', $value)
        ) {
            return '0x' . \strtoupper(\bin2hex($value));
        }

        return Debug::sanitizeData($value);
    }

    /*
     * Return the query with the values put into the ? placeholders
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

        return \preg_replace_callback(
            '/\?/',
            function () use ($parameters, &$i): string {
                $result = $this->escapeValue($parameters[$i] ?? null);
                $i++;

                return $result;
            },
            $query,
        ) ?? '';
    }

    public function prettifySql(string $sql): string
    {
        return $this->getSqlFormatter()->highlight($sql);
    }

    public function formatSql(string $sql, bool $highlight): string
    {
        return $this->getSqlFormatter($highlight)->format($sql);
    }

    private function getSqlFormatter(bool $highlight = true): SqlFormatter
    {
        return new SqlFormatter($highlight ? new HtmlHighlighter([
            HtmlHighlighter::HIGHLIGHT_PRE            => 'class="highlight highlight-sql"',
            HtmlHighlighter::HIGHLIGHT_QUOTE          => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_RESERVED       => 'class="keyword"',
            HtmlHighlighter::HIGHLIGHT_BOUNDARY       => 'class="symbol"',
            HtmlHighlighter::HIGHLIGHT_NUMBER         => 'class="number"',
            HtmlHighlighter::HIGHLIGHT_WORD           => 'class="word"',
            HtmlHighlighter::HIGHLIGHT_ERROR          => 'class="error"',
            HtmlHighlighter::HIGHLIGHT_COMMENT        => 'class="comment"',
            HtmlHighlighter::HIGHLIGHT_VARIABLE       => 'class="variable"',
        ]) : new NullHighlighter());
    }
}
