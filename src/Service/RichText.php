<?php
declare(strict_types=1);

namespace App\Service;

use DOMDocument;
use DOMElement;
use HTMLPurifier;
use HTMLPurifier_Config;
use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use RuntimeException;

class RichText
{
    private static ?HTMLPurifier $purifier = null;

    /** @return string */
    public static function sanitize(string $html): string
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', CACHE);
            $config->set('HTML.DefinitionID', 'sjhwiki-rich-text');
            $config->set('HTML.DefinitionRev', 2);
            $languagesFile = ROOT . DS . 'resources' . DS . 'highlight-languages.json';
            if (!is_file($languagesFile)) {
                throw new RuntimeException('Syntax highlighting assets are missing. Run npm ci and npm run build.');
            }
            $languages = json_decode((string)file_get_contents($languagesFile), true, 512, JSON_THROW_ON_ERROR);
            $config->set('Attr.AllowedClasses', array_map(
                static fn(string $language): string => 'language-' . $language,
                $languages,
            ));
            $config->set(
                'HTML.Allowed',
                'p[style],br,strong,b,em,i,u,s,del,code[class],pre,blockquote,ul,ol[start],li,' .
                'h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],hr,sub,sup,' .
                'a[href|title|target|rel],img[src|alt|title|width|height],span[style],div[style],' .
                'table[style],colgroup,col[style|width],thead,tbody,tfoot,tr,' .
                'th[colspan|rowspan|colwidth|style],td[colspan|rowspan|colwidth|style]',
            );
            $config->set('CSS.AllowedProperties', [
                'color', 'background-color', 'font-family', 'font-size', 'text-align', 'text-decoration',
                'width', 'min-width',
            ]);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $definition = $config->maybeGetRawHTMLDefinition();
            if ($definition !== null) {
                $definition->addAttribute('td', 'colwidth', new ColumnWidths());
                $definition->addAttribute('th', 'colwidth', new ColumnWidths());
            }
            self::$purifier = new HTMLPurifier($config);
        }

        $clean = self::$purifier->purify($html);
        if (!str_contains($clean, '<tfoot')) {
            return $clean;
        }

        return self::footerLast($clean);
    }

    /** @return string */
    private static function footerLast(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        if (!$loaded) {
            throw new RuntimeException('Cannot process the sanitized table.');
        }
        foreach ($document->getElementsByTagName('table') as $table) {
            foreach (iterator_to_array($table->childNodes) as $child) {
                if ($child instanceof DOMElement && $child->tagName === 'tfoot') {
                    $table->appendChild($child);
                }
            }
        }
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$body) {
            throw new RuntimeException('Cannot process the sanitized page.');
        }
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /** @return string */
    public static function markdown(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
        $html = $converter->convert($markdown)->getContent();
        $html = preg_replace_callback('/<input\b[^>]*\btype="checkbox"[^>]*\/?>/i', static function ($match) {
            return preg_match('/\bchecked(?:\s|=|\/?>)/i', $match[0]) ? '[x] ' : '[ ] ';
        }, $html);

        return self::sanitize($html);
    }

    /** @return string */
    public static function render(string $body, string $format): string
    {
        return match ($format) {
            'markdown' => self::markdown($body),
            'html' => self::sanitize($body),
            default => throw new InvalidArgumentException('Unknown page content format.'),
        };
    }
}
