<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * Provide for specific Gettext related helper functionality.
 *
 * @see http://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 * @author Clemens Tolboom
 * @copyright Clemens Tolboom clemens@build2be.com
 */
class Gettext
{
    /**
     * Defines a key for managing a PO Header in our messages.
     *
     * PO and MO files can have a header which needs to be managed.
     * Currently we choose to let this be done by PoFileLoader static function. 
     */
    const HEADER_KEY = "__HEADER__";

    /**
     * Parses a Gettext header string into a key/value pairs.
     *
     * @param $header
     *   The Gettext header.
     * @return array
     *   Array with the key/value pair
     */
    static function explodeHeader($header)
    {
        $clean_extra = FALSE;
        $result = array();
        $lines = explode("\n", $header);
        foreach ($lines as $line) {
            $cleaned = trim($line);
            if ($cleaned == 'msgid ""') {
                $clean_extra = TRUE;
            }
            if (strpos($cleaned, ':') > 0) {
                list($key, $value) = explode(':', $cleaned, 2);
                $key = trim($key);
                if ($clean_extra) {
                    // Delete prefix: "
                    $key = substr($key, 1);
                    // Delete postfix \n"
                   $value = substr($value, 0, -3);
                }
                $result[$key] = trim($value);
            }
        }
        return $result;
    }

    /**
     * Merge key/value pair into Gettext compatible item.
     *
     * Each combination is into substring: "key: value \n".
     *
     * If any key found the values are preceded by empty msgid and msgstr
     *
     * @param array $header
     * @return array or NULL
     *   A Gettext compatible item.
     */
    static function implodeHeader(array $header)
    {
       $lines = array();
       foreach ($header as $key => $value) {
           $lines[] = '"' . $key . ": " . $value . '\n"';
       }
       if (count($lines)) {
         $result = array(
           'msgid ""',
           'msgstr ""',
         );
         $result = array_merge($result, $lines);
         return implode("\n", $result);
       }
    }

    /**
     * Ordered list of Gettext header keys
     *
     * TODO: this list is probably incomplete
     *
     * @return array
     *   Ordered list of Gettext keys
     */
    static function headerKeys() {
        return array(
            'Project-Id-Version',
            'POT-Creation-Date',
            'PO-Revision-Date',
            'Last-Translator',
            'Language-Team',
            'MIME-Version',
            'Content-Type',
            'Content-Transfer-Encoding',
            'Plural-Forms'
        );
    }

    static function emptyHeader() {
        return array_fill_keys(Gettext::headerKeys(), "");
    }

    /**
     * Retrieve PO Header from messages.
     *
     * @param array $messages
     * @return the found message or NULL
     */
    static function getHeader(array &$messages)
    {
        if (isset($messages[Gettext::HEADER_KEY])) {
          return $messages[Gettext::HEADER_KEY];
        }
        return NULL;
    }

    /**
     * Adds or overwrite a header to the messages.
     *
     * @param array $messages
     * @param type $header 
     */
    static function addHeader(array &$messages, $header)
    {
        $messages[Gettext::HEADER_KEY] = $header;
    }

    /**
     * Deletes a header from the messages if exists.
     *
     * @param array $messages 
     */
    static function delHeader(array &$messages) {
        if (isset($messages[Gettext::HEADER_KEY])) {
          unset($messages[Gettext::HEADER_KEY]);
        }
    }

}
