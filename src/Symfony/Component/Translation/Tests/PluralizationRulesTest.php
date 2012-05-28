<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use Symfony\Component\Translation\PluralizationRules;

/**
 * Test should cover all languages mentioned on http://translate.sourceforge.net/wiki/l10n/pluralforms
 * and Plural forms mentioned on http://www.gnu.org/software/gettext/manual/gettext.html#Plural-forms
 *
 * See also https://developer.mozilla.org/en/Localization_and_Plurals which mentions 15 rules having a maximum of 6 forms.
 * The mozilla code is also interesting to check for.
 *
 * As mentioned by chx http://drupal.org/node/1273968 we can cover all by testing number from 0 to 199
 *
 * The goal to cover all languagues is to far fetched so this test case is smaller.
 *
 * @author Clemens Tolboom clemens@build2be.nl
 */
class PluralizationRulesTest extends \PHPUnit_Framework_TestCase
{

  /**
   * Tests the differences between Drupal, Sourceforge and Symfony
   *
   * This test fails as there are missing langcodes in symfony.
   * One difference is sub langcodes like pt_BR or nl_BE
   * TODO:
   * - we need to report a bug about this.
   */
  function testLangcodesKnownByOthers()
  {
      $symfony = $this->symfonyLangCodes();
      $sourceforge = $this->sourceforgeLangCodes();
      $drupal = $this->drupalLangcodes();

      $allLangcodes = array_merge($symfony, $sourceforge, $drupal);
      $allLangcodes = array_flip($allLangcodes);
      $allLangcodes = array_keys($allLangcodes);
      asort($allLangcodes);
      $allLangcodes = array_values($allLangcodes);
      $report = array();

      $diff = array_diff($allLangcodes, $symfony);
      $report[] = "Symfony (" . count($symfony) . ") : misses (".count($diff).") " . implode(', ', $diff);

      $diff = array_diff($allLangcodes, $drupal);
      $report[] = "Drupal (" . count($drupal) . ") : misses (".count($diff).") " . implode(', ', $diff);

      $diff = array_diff($allLangcodes, $sourceforge);
      $report[] = "Sourceforge (" . count($sourceforge) . ") : misses (".count($diff).") " . implode(', ', $diff);

      $report[] = "Total: " . count($allLangcodes);

      $this->markTestIncomplete(implode("\n", $report));

      $this->assertEquals($symfony, $drupal, "Contains all langcodes reported by Drupal.");
      $this->assertEquals($symfony, $sourceforge, "Contains all langcodes reported by Sourceforge.");
  }

  /**
   * Tests the plural for reported by Sourceforge page.
   *
   * @see http://translate.sourceforge.net/wiki/l10n/pluralforms
   *
   */
  function testPluralFormMatchesSourceforge()
  {
      $symfony = $this->symfonyLangCodes();
      $sourceforge = $this->sourceForcePluralRules();
      $tests = array();
      foreach ($symfony as $langcode) {
          if (isset($sourceforge[$langcode])) {
              $pluralForm = $sourceforge[$langcode];
              list($nplural, $rule) = $this->splitPluralForm($pluralForm);
              $symfonyCoded = $this->nPluralByMatrix($langcode);
              if ($nplural != $symfonyCoded) {
                  $tests[$nplural][$langcode] = "Symfony($symfonyCoded) <-> " . $pluralForm;
              }
          }
      }
      $this->listByPluralLangcode($tests, "Mismatch by nplural (number of plural forms).");
      $this->markTestIncomplete("PluralForm don't match between hardcoded Symfony and Sourceforge definition.");
  }

  /**
   * Tests the Sourceforge PluralForm against Symfony.
   *
   * The pattern taken from the 200 $count variants are compared
   * against each other.
   *
   * A mismatch can occur due to $plural which are skipped.
   * @see testPluralFormMatchesSourceforge
   *
   * A mismatch when $plural is equal can occur due to:
   * - 1. Other indexes used but similar. This is not useful for symfony.
   * - 2. Not even a similar pattern. This indicates a bug on either side.
   */
  function testPluralizationMatchWithOthers()
  {
      $this->assertTrue($this->hasSamePattern("0120", "0120"), 'Patterns are the same.');
      $this->assertFalse($this->hasSamePattern("0121", "0120"), 'Patterns differ.');
      $this->assertTrue($this->hasSameNormalizedPattern("0120", "1201"), 'Patterns are similar.');
      $this->assertFalse($this->hasSameNormalizedPattern("0120", "6543"), 'Patterns are not even similar.');

      $symfony = $this->symfonyMatrixes();
      $sourceforge = $this->sourceforceMatrixes();
      $match = array();
      $skipped = array();
      $normalized = array();
      $mismatch = array();

      foreach ($symfony as $plural => $langcodes) {
          foreach ($langcodes as $langcode => $symfonyMatrix) {
              // We skip langcodes with different plurals
              if (isset($sourceforge[$plural]) && isset($sourceforge[$plural][$langcode])) {
                  // Make it a string for simple testing.
                  $symfonyMap = implode("", $symfonyMatrix);
                  $sourceforgeMap = implode("", $sourceforge[$plural][$langcode]);
                  // Both patterns must be equal.
                  if ($this->hasSamePattern($sourceforgeMap, $symfonyMap)) {
                      $match[$plural][$langcode] = 'match';
                  } else {
                    if ($this->hasSameNormalizedPattern($sourceforgeMap, $symfonyMap)) {
                        // Normalization means they are similar but not useful
                        $normalized[$plural][$langcode]['sym'] = $symfonyMap;
                        $normalized[$plural][$langcode]['sf'] = $sourceforgeMap;
                    } else {
                        // Not even similar means a bug.
                        $mismatch[$plural][$langcode]['sym'] = $symfonyMap;
                        $mismatch[$plural][$langcode]['sf'] = $sourceforgeMap;
                        $mismatch[$plural][$langcode]['sym_nor'] = $this->normalizePattern($symfonyMap);
                        $mismatch[$plural][$langcode]['sf_nor'] = $this->normalizePattern($sourceforgeMap);
                    }
                  }
              } else {
                  // We do not have the langcode available.
                  $skipped[$plural][$langcode] = 'skipped';
              }
          }
      }

      //$this->markTestIncomplete("We must solve mismatches and/or similar but not equal.");

      $this->listByPluralLangcodeSource($mismatch, "Mismatch: real errors.");
      $this->listByPluralLangcodeSource($normalized, "Normalized but not equal: could cause import problems.");

      $this->assertEquals(array(), $mismatch, 'We should not have any mismatches between plural rules.');
      $this->assertEquals(array(), $normalized, 'We should not have any normalized matching plural rules.');
      $this->assertEquals(array(), $skipped, 'We prefer to have ALL langcode implemented.');
  }

  public function testGenerateFullMatrix()
  {
      //$this->dumpSymfonyMatrixes();
      //$this->dumpSourceforgeMatrixes();
  }

  public function testPluralRules()
  {
      $pluralMatrixes = $this->sourceforceMatrixes();
      $symfonyMatrixes = $this->symfonyMatrixes();

      $mismatch = array();
      foreach ($symfonyMatrixes as $plural => $data) {
          $ours = array_keys($data);
          ksort($ours);
          $theirs = array_keys($pluralMatrixes[$plural]);
          ksort($theirs);
          $diff = array_diff($ours, $theirs);
          if (!empty($diff)) {
              $mismatch[$plural] = $plural . " : " . implode(" ", $diff);
          }
      }
      $mismatch = implode("; ", $mismatch);
      $this->markTestSkipped("The languages have different nplurals according to sourceforge. " . $mismatch);

      $this->assertEquals('', $mismatch, 'nplurals must match');
  }

  public function testMissingLangcodesOnSymfony()
  {
      $this->markTestSkipped("We need more lang codes");
      $symfony = $this->symfonyLangCodes();
      $sourceforge = array_keys($this->sourceforgeLangCodes());

      $missingOnSymfony = array_diff($sourceforge, $symfony);
      $this->assertEquals(array(), $missingOnSymfony, "Symfony misses langcodes listed on sourceforge.");
  }

  public function testMissingLangcodesOnSourceforge()
  {
      $this->markTestSkipped("We could help Sourceforge with more lang codes");
      $symfony = $this->symfonyLangCodes();
      $sourceforge = array_keys($this->sourceforgeLangCodes());

      $missingOnSourceforge = array_diff($symfony, $sourceforge);
      $this->assertEquals(array(), $missingOnSourceforge, "Symfony has additional langcodes compared to sourceforge.");
  }

  function listByPluralLangcode($list, $message)
  {
      echo "\n$message\n";
      foreach ($list as $plural => $data) {
          foreach ($data as $langCode => $value) {
              echo "$plural\t$langCode\t$value\n";
          }
          echo "\n";
      }
  }

  function listByPluralLangcodeSource($list, $message)
  {
      echo "\n$message\n";
      foreach ($list as $plural =>$data) {
        foreach ($data as $langCode => $src) {
          foreach ($src as $type => $value) {
            echo "$plural\t$langCode\t$type\t$value\n";
          }
          echo "\n";
        }
      }

  }

  /**
   * Helper function
   *
   * @param string $pat1 contains numbers only.
   * @param string $pat2 contains numbers only.
   * @return boolean Patterns are equal.
   */
  function hasSamePattern($pat1, $pat2)
  {
      if ($pat1 == $pat2) {
        return true;
      }

      return false;
  }

  /**
   * Tests for similar patterns
   *
   * @param string $pat1 contains numbers only.
   * @param string $pat2 contains numbers only.
   * @return boolean Patterns are similar.
   */
  function hasSameNormalizedPattern($pat1, $pat2)
  {
      $p1 = $this->normalizePattern($pat1);
      $p2 = $this->normalizePattern($pat2);

      return $this->hasSamePattern($p1, $p2);
  }

  /**
   * Normalizes the given pattern.
   *
   * Normalizing means replacing same occurrences with the same letter
   * from the alfabet beginning with the A for position 0.
   *
   * @param string $pat contains numbers only.
   * @return string Pattern containing AB... similar to given $pat.
   */
  function normalizePattern($pat)
  {
      // We have maximum of 6 PluralForms so we take first 8 (6+2) letters.
      $alfabet = preg_split("//", 'ABCDEFGH',-1);
      $p = preg_split('//', $pat, -1);
      $code = array();
      // Plural $count == 1 aka Singular for has index 0.
      $code[$p[1]] = 'A';
      array_shift($alfabet);
      unset($p[1]);
      while ($p) {
          $n = array_shift($p);
          if (!isset($code[$n])) {
              $code["$n"] = array_shift($alfabet);
          }
      }

      return str_replace(array_keys($code), array_values($code), $pat);
  }

  private function nPluralByMatrix($langcode)
  {
      $m = $this->generateTestData(array($langcode));

      return count(array_flip($m[$langcode]));
  }

  private function symfonyMatrixes()
  {
      $matrixes = array();
      $langCodes = $this->symfonyLangCodes();
      foreach ($langCodes as $langCode) {
          $result = array();
          for ($count = 0; $count < 200; $count++) {
              $result[$count] = PluralizationRules::get($count, $langCode);
          }
          $nplural = count(array_flip($result));
          $matrixes[$nplural][$langCode] = $result;
      }
      ksort($matrixes);

      return $matrixes;
  }

  function dumpSymfonyMatrixes()
  {
      $matrixes = $this->symfonyMatrixes();
      foreach ($matrixes as $nplural => $matrix) {
          $this->matrixToString($matrix, $nplural);
      }
  }

  /**
   * This array should contain all currently known langcodes.
   *
   * As it is impossible to have this ever complete we should try as hard as possible to have it almost complete.
   *
   * @return type
   */
  public function successLangcodes()
  {
    return array(
      array('1',
        array('ay',
          'bo',
          'cgg',
          'dz',
          'id',
          'ja',
          'jbo',
          'ka',
          'kk',
          'km',
          'ko',
          'ky')),
      array('2',
        array('nl',
          'fr',
          'en',
          'de',
          'de_GE')),
      array('3',
        array('be',
          'bs',
          'cs',
          'hr')),
      array('4',
        array('cy',
          'mt',
          'sl')),
      array('5',
        array()),
      array('6',
        array('ar')),
    );
  }

  private function dumpSourceforgeMatrixes()
  {
      $matrixes = $this->sourceforceMatrixes();
      foreach ($matrixes as $nplural => $matrix) {
          $this->matrixToString($matrix, $nplural, false);
      }
  }

  private function splitPluralForm($pluralForm)
  {
      list($xnplural, $rule) = explode(';', $pluralForm, 2);
      list($dummy, $nplural) = explode('=', $xnplural);
      $nplural = intval($nplural);

      return array($nplural, $rule);
  }

  private function sourceforceMatrixes()
  {
      $symfony = $this->symfonyLangCodes();
      $sourceforge = $this->sourceForcePluralRules();
      $matrixes = array();
      foreach ($symfony as $langcode) {
          if (isset($sourceforge[$langcode])) {
              $pluralForm = $sourceforge[$langcode];
              list($nplural, $rule) = $this->splitPluralForm($pluralForm);
              $rule = preg_replace(array('/plural/', '/n/','/\;/'), array('\$plural', '\$n',''), $rule);
              for ($n=0;$n<200;$n++) {
                  $expression = '$n = ' . $n . ';' . $rule . '; return intval($plural);';
                  $value = eval($expression);
                  if ($value === false) {
                      $value ="-";
                  }
                  $matrixes[$nplural][$langcode][$n] = $value;
              }
          }
      }
      ksort($matrixes);

      return $matrixes;
  }

  /**
   * We validate only on the plural coverage. Thus the real rules is not tested.
   *
   * @param string  $nplural       plural expected
   * @param array   $matrix        containing langcodes and their plural index values.
   * @param boolean $expectSuccess
   */
  protected function validateMatrixPluralCoverage($nplural, $matrix)
  {
    foreach ($matrix as $langCode => $data) {
      $indexes = array_flip($data);
      $this->assertEquals($nplural, count($indexes), "Langcode '$langCode' has '$nplural' plural forms.");
    }
  }

  protected function generateTestData($langCodes)
  {
    $matrix = array();
    foreach ($langCodes as $langCode) {
      for ($count = 0; $count < 200; $count++) {
        $plural = PluralizationRules::get($count, $langCode);
        $matrix[$langCode][$count] = $plural;
      }
    }

    return $matrix;
  }

  private function matrixToString($matrix, $nplural = null, $useTabs = false)
  {
    //return;
    $sep = $useTabs ? "\t" : "";
    foreach ($matrix as $langCode => $data) {
      if (!is_null($nplural)) {
        echo "$nplural\t";
      }
      print_r($langCode . "\t" . implode($sep, $data) . "\n");
    }
  }

  function sourceforgeLangCodes()
  {
    return array_keys($this->sourceForcePluralRules());
  }

  // BEGIN PASTE FROM SOURCES

  /**
   * All langcodes used in PluralizationRules.
   *
   * Done by
   * grep \' src/Symfony/Component/Translation/PluralizationRules.php | sort -u | cut -c 18- | grep \: | cut -d ':' -f 1 | xargs -I {} echo \'{}\',
   *
   * TODO: can we grep sourcecode by Reflection?
   *
   * @return array containing langcodes
   */
  protected function symfonyLangCodes()
  {
    $additional = array('pt_BR');
    $virtuals = array('xbr');
    $grepped = array(
      'af',
      'am',
      'ar',
      'az',
      'be',
      'bg',
      'bh',
      'bn',
      'bo',
      'bs',
      'ca',
      'cs',
      'cy',
      'da',
      'de',
      'dz',
      'el',
      'en',
      'eo',
      'es',
      'et',
      'eu',
      'fa',
      'fi',
      'fil',
      'fo',
      'fr',
      'fur',
      'fy',
      'ga',
      'gl',
      'gu',
      'gun',
      'ha',
      'he',
      'hi',
      'hr',
      'hu',
      'id',
      'is',
      'it',
      'ja',
      'jv',
      'ka',
      'km',
      'kn',
      'ko',
      'ku',
      'lb',
      'ln',
      'lt',
      'lv',
      'mg',
      'mk',
      'ml',
      'mn',
      'mr',
      'ms',
      'mt',
      'nah',
      'nb',
      'ne',
      'nl',
      'nn',
      'no',
      'nso',
      'om',
      'or',
      'pa',
      'pap',
      'pl',
      'ps',
      'pt',
      'ro',
      'ru',
      'sk',
      'sl',
      'so',
      'sq',
      'sr',
      'sv',
      'sw',
      'ta',
      'te',
      'th',
      'ti',
      'tk',
      'tr',
      'uk',
      'ur',
      'vi',
      'wa',
      'xbr',
      'zh',
      'zu',
    );

    return array_diff(array_merge($additional, $grepped), $virtuals);
  }

  /**
   * Langcodes from http://translate.sourceforge.net/wiki/l10n/pluralforms
   *
   * Script taken from http://build2be.com/content/langcodes-and-plural-forms to
   * grab all level2 tr>td:first and tr>td:last
   *
   * @return array langcode, plural string
   */
  function sourceForcePluralRules()
  {
    return
        array(
          'ach' => 'nplurals=2; plural=(n > 1)',
          'af' => 'nplurals=2; plural=(n != 1)',
          'ak' => 'nplurals=2; plural=(n > 1)',
          'am' => 'nplurals=2; plural=(n > 1)',
          'an' => 'nplurals=2; plural=(n != 1)',
          'ar' => 'nplurals=6; plural= n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5;',
          'arn' => 'nplurals=2; plural=(n > 1)',
          'ast' => 'nplurals=2; plural=(n != 1)',
          'ay' => 'nplurals=1; plural=0',
          'az' => 'nplurals=2; plural=(n != 1)',
          'be' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'bg' => 'nplurals=2; plural=(n != 1)',
          'bn' => 'nplurals=2; plural=(n != 1)',
          'bo' => 'nplurals=1; plural=0',
          'br' => 'nplurals=2; plural=(n > 1)',
          'bs' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'ca' => 'nplurals=2; plural=(n != 1)',
          'cgg' => 'nplurals=1; plural=0',
          'cs' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2',
          'csb' => 'nplurals=3; n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2',
          'cy' => 'nplurals=4; plural= (n==1) ? 0 : (n==2) ? 1 : (n != 8 && n != 11) ? 2 : 3',
          'da' => 'nplurals=2; plural=(n != 1)',
          'de' => 'nplurals=2; plural=(n != 1)',
          'dz' => 'nplurals=1; plural=0',
          'el' => 'nplurals=2; plural=(n != 1)',
          'en' => 'nplurals=2; plural=(n != 1)',
          'eo' => 'nplurals=2; plural=(n != 1)',
          'es' => 'nplurals=2; plural=(n != 1)',
          'es_AR' => 'nplurals=2; plural=(n != 1)',
          'et' => 'nplurals=2; plural=(n != 1)',
          'eu' => 'nplurals=2; plural=(n != 1)',
          'fa' => 'nplurals=1; plural=0',
          'fi' => 'nplurals=2; plural=(n != 1)',
          'fil' => 'nplurals=2; plural=n > 1',
          'fo' => 'nplurals=2; plural=(n != 1)',
          'fr' => 'nplurals=2; plural=(n > 1)',
          'fur' => 'nplurals=2; plural=(n != 1)',
          'fy' => 'nplurals=2; plural=(n != 1)',
          'ga' => 'nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : n<7 ? 2 : n<11 ? 3 : 4',
          'gd' => 'nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n > 2 && n < 20) ? 2 : 3',
          'gl' => 'nplurals=2; plural=(n != 1)',
          'gu' => 'nplurals=2; plural=(n != 1)',
          'gun' => 'nplurals=2; plural = (n > 1)',
          'ha' => 'nplurals=2; plural=(n != 1)',
          'he' => 'nplurals=2; plural=(n != 1)',
          'hi' => 'nplurals=2; plural=(n != 1)',
          'hy' => 'nplurals=2; plural=(n != 1)',
          'hr' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'hu' => 'nplurals=2; plural=(n != 1)',
          'ia' => 'nplurals=2; plural=(n != 1)',
          'id' => 'nplurals=1; plural=0',
          'is' => 'nplurals=2; plural=(n%10!=1 || n%100==11)',
          'it' => 'nplurals=2; plural=(n != 1)',
          'ja' => 'nplurals=1; plural=0',
          'jbo' => 'nplurals=1; plural=0',
          'jv' => 'nplurals=2; plural=n!=0',
          'ka' => 'nplurals=1; plural=0',
          'kk' => 'nplurals=1; plural=0',
          'km' => 'nplurals=1; plural=0',
          'kn' => 'nplurals=2; plural=(n!=1)',
          'ko' => 'nplurals=1; plural=0',
          'ku' => 'nplurals=2; plural=(n!= 1)',
          'kw' => 'nplurals=4; plural= (n==1) ? 0 : (n==2) ? 1 : (n == 3) ? 2 : 3',
          'ky' => 'nplurals=1; plural=0',
          'lb' => 'nplurals=2; plural=(n != 1)',
          'ln' => 'nplurals=2; plural=n>1;',
          'lo' => 'nplurals=1; plural=0',
          'lt' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 or n%100>=20) ? 1 : 2)',
          'lv' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2)',
          'mai' => 'nplurals=2; plural=(n != 1)',
          'mfe' => 'nplurals=2; plural=(n > 1)',
          'mg' => 'nplurals=2; plural=(n > 1)',
          'mi' => 'nplurals=2; plural=(n > 1)',
          'mk' => 'nplurals=2; plural= n==1 || n%10==1 ? 0 : 1',
          'ml' => 'nplurals=2; plural=(n != 1)',
          'mn' => 'nplurals=2; plural=(n != 1)',
          'mnk' => 'nplurals=3; plural=(n==0 ? 0 : n==1 ? 1 : 2',
          'mr' => 'nplurals=2; plural=(n != 1)',
          'ms' => 'nplurals=1; plural=0',
          'mt' => 'nplurals=4; plural=(n==1 ? 0 : n==0 || ( n%100>1 && n%100<11) ? 1 : (n%100>10 && n%100<20 ) ? 2 : 3)',
          'nah' => 'nplurals=2; plural=(n != 1)',
          'nap' => 'nplurals=2; plural=(n != 1)',
          'nb' => 'nplurals=2; plural=(n != 1)',
          'ne' => 'nplurals=2; plural=(n != 1)',
          'nl' => 'nplurals=2; plural=(n != 1)',
          'se' => 'nplurals=2; plural=(n != 1)',
          'nn' => 'nplurals=2; plural=(n != 1)',
          'no' => 'nplurals=2; plural=(n != 1)',
          'nso' => 'nplurals=2; plural=(n != 1)',
          'oc' => 'nplurals=2; plural=(n > 1)',
          'or' => 'nplurals=2; plural=(n != 1)',
          'ps' => 'nplurals=2; plural=(n != 1)',
          'pa' => 'nplurals=2; plural=(n != 1)',
          'pap' => 'nplurals=2; plural=(n != 1)',
          'pl' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'pms' => 'nplurals=2; plural=(n != 1)',
          'pt' => 'nplurals=2; plural=(n != 1)',
          'pt_BR' => 'nplurals=2; plural=(n != 1)',
          'rm' => 'nplurals=2; plural=(n!=1);',
          'ro' => 'nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100 > 0 && n%100 < 20)) ? 1 : 2);',
          'ru' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'sah' => 'nplurals=1; plural=0',
          'sco' => 'nplurals=2; plural=(n != 1)',
          'si' => 'nplurals=2; plural=(n != 1)',
          'sk' => 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2',
          'sl' => 'nplurals=4; plural=(n%100==1 ? 1 : n%100==2 ? 2 : n%100==3 || n%100==4 ? 3 : 0)',
          'so' => 'nplurals=2; plural=n != 1',
          'son' => 'nplurals=2; plural=(n != 1)',
          'sq' => 'nplurals=2; plural=(n != 1)',
          'sr' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'su' => 'nplurals=1; plural=0',
          'sw' => 'nplurals=2; plural=(n != 1)',
          'sv' => 'nplurals=2; plural=(n != 1)',
          'ta' => 'nplurals=2; plural=(n != 1)',
          'te' => 'nplurals=2; plural=(n != 1)',
          'tg' => 'nplurals=2; plural=(n > 1)',
          'ti' => 'nplurals=2; plural=n > 1',
          'th' => 'nplurals=1; plural=0',
          'tk' => 'nplurals=2; plural=(n != 1)',
          'tr' => 'nplurals=2; plural=(n>1)',
          'tt' => 'nplurals=1; plural=0',
          'ug' => 'nplurals=1; plural=0;',
          'uk' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2)',
          'ur' => 'nplurals=2; plural=(n != 1)',
          'uz' => 'nplurals=2; plural=(n > 1)',
          'vi' => 'nplurals=1; plural=0',
          'wa' => 'nplurals=2; plural=(n > 1)',
          'wo' => 'nplurals=1; plural=0',
          'yo' => 'nplurals=2; plural=(n != 1)',
          'zh' => 'nplurals=1; plural=0',
          'zh' => 'nplurals=2; plural=(n > 1)',
    );
  }

  /**
   * Languages define in Drupal core/includes.inc
   *
   * Copy/paste of $countries
   *
   */
  private function drupalLangcodes()
  {
    //helper function to ease the paste
    $t = function($x) { return $x;};
    $langcodes =
    // Drupal paste: replace LANGUAGE_RTL by 'LANGUAGE_RTL'
  array(
    'af' => array('Afrikaans', 'Afrikaans'),
    'am' => array('Amharic', 'አማርኛ'),
    'ar' => array('Arabic', /* Left-to-right marker "‭" */ 'العربية', 'LANGUAGE_RTL'),
    'ast' => array('Asturian', 'Asturianu'),
    'az' => array('Azerbaijani', 'Azərbaycanca'),
    'be' => array('Belarusian', 'Беларуская'),
    'bg' => array('Bulgarian', 'Български'),
    'bn' => array('Bengali', 'বাংলা'),
    'bo' => array('Tibetan', 'བོད་སྐད་'),
    'bs' => array('Bosnian', 'Bosanski'),
    'ca' => array('Catalan', 'Català'),
    'cs' => array('Czech', 'Čeština'),
    'cy' => array('Welsh', 'Cymraeg'),
    'da' => array('Danish', 'Dansk'),
    'de' => array('German', 'Deutsch'),
    'dz' => array('Dzongkha', 'རྫོང་ཁ'),
    'el' => array('Greek', 'Ελληνικά'),
    'en' => array('English', 'English'),
    'en-gb' => array('English, British', 'English, British'),
    'eo' => array('Esperanto', 'Esperanto'),
    'es' => array('Spanish', 'Español'),
    'et' => array('Estonian', 'Eesti'),
    'eu' => array('Basque', 'Euskera'),
    'fa' => array('Persian, Farsi', /* Left-to-right marker "‭" */ 'فارسی', 'LANGUAGE_RTL'),
    'fi' => array('Finnish', 'Suomi'),
    'fil' => array('Filipino', 'Filipino'),
    'fo' => array('Faeroese', 'Føroyskt'),
    'fr' => array('French', 'Français'),
    'ga' => array('Irish', 'Gaeilge'),
    'gd' => array('Scots Gaelic', 'Gàidhlig'),
    'gl' => array('Galician', 'Galego'),
    'gsw-berne' => array('Swiss German', 'Schwyzerdütsch'),
    'gu' => array('Gujarati', 'ગુજરાતી'),
    'he' => array('Hebrew', /* Left-to-right marker "‭" */ 'עברית', 'LANGUAGE_RTL'),
    'hi' => array('Hindi', 'हिन्दी'),
    'hr' => array('Croatian', 'Hrvatski'),
    'ht' => array('Haitian Creole', 'Kreyòl ayisyen'),
    'hu' => array('Hungarian', 'Magyar'),
    'hy' => array('Armenian', 'Հայերեն'),
    'id' => array('Indonesian', 'Bahasa Indonesia'),
    'is' => array('Icelandic', 'Íslenska'),
    'it' => array('Italian', 'Italiano'),
    'ja' => array('Japanese', '日本語'),
    'jv' => array('Javanese', 'Basa Java'),
    'ka' => array('Georgian', 'ქართული ენა'),
    'kk' => array('Kazakh', 'Қазақ'),
    'kn' => array('Kannada', 'ಕನ್ನಡ'),
    'ko' => array('Korean', '한국어'),
    'ku' => array('Kurdish', 'Kurdî'),
    'ky' => array('Kyrgyz', 'Кыргызча'),
    'lo' => array('Lao', 'ພາສາລາວ'),
    'lt' => array('Lithuanian', 'Lietuvių'),
    'lv' => array('Latvian', 'Latviešu'),
    'mfe' => array('Mauritian Creole', 'Kreol Morisyen'),
    'mg' => array('Malagasy', 'Malagasy'),
    'mi' => array('Maori', 'Māori'),
    'mk' => array('Macedonian', 'Македонски'),
    'ml' => array('Malayalam', 'മലയാളം'),
    'mn' => array('Mongolian', 'монгол'),
    'mr' => array('Marathi', 'मराठी'),
    'mt' => array('Maltese', 'Malti'),
    'my' => array('Burmese', 'ဗမာစကား'),
    'ne' => array('Nepali', 'नेपाली'),
    'nl' => array('Dutch', 'Nederlands'),
    'nb' => array('Norwegian Bokmål', 'Bokmål'),
    'nn' => array('Norwegian Nynorsk', 'Nynorsk'),
    'oc' => array('Occitan', 'Occitan'),
    'or' => array('Oriya', 'ଓଡ଼ିଆ'),
    'pa' => array('Punjabi', 'ਪੰਜਾਬੀ'),
    'pl' => array('Polish', 'Polski'),
    'pt' => array('Portuguese, International', 'Português, Internacional'),
    'pt-pt' => array('Portuguese, Portugal', 'Português, Portugal'),
    'pt-br' => array('Portuguese, Brazil', 'Português, Brasil'),
    'ro' => array('Romanian', 'Română'),
    'ru' => array('Russian', 'Русский'),
    'sco' => array('Scots', 'Scots'),
    'se' => array('Northern Sami', 'Sámi'),
    'si' => array('Sinhala', 'සිංහල'),
    'sk' => array('Slovak', 'Slovenčina'),
    'sl' => array('Slovenian', 'Slovenščina'),
    'sq' => array('Albanian', 'Shqip'),
    'sr' => array('Serbian', 'Српски'),
    'sv' => array('Swedish', 'Svenska'),
    'sw' => array('Swahili', 'Kiswahili'),
    'ta' => array('Tamil', 'தமிழ்'),
    'ta-lk' => array('Tamil, Sri Lanka', 'தமிழ், இலங்கை'),
    'te' => array('Telugu', 'తెలుగు'),
    'th' => array('Thai', 'ภาษาไทย'),
    'ti' => array('Tigrinya', 'ትግርኛ'),
    'tr' => array('Turkish', 'Türkçe'),
    'ug' => array('Uighur', 'Уйғур'),
    'uk' => array('Ukrainian', 'Українська'),
    'ur' => array('Urdu', /* Left-to-right marker "‭" */ 'اردو', 'LANGUAGE_RTL'),
    'vi' => array('Vietnamese', 'Tiếng Việt'),
    'xx-lolspeak' => array('Lolspeak', 'Lolspeak'),
    'zh-hans' => array('Chinese, Simplified', '简体中文'),
    'zh-hant' => array('Chinese, Traditional', '繁體中文'),
  );
    unset($langcodes['xx-lolspeak']);
      // end drupal paste
      return array_keys($langcodes);

  }

  //END PASTE FROM SOURCES
}
