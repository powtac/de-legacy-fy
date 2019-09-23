<?php
/*
 * This file is part of de-legacy-fy.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace SebastianBergmann\DeLegacyFy;

class XdebugTraceParser
{
    /**
     * @param string $filename
     * @param string $unit
     *
     * @return array
     */
    public function parse($filename, $unit)
    {
        $data       = [];
        $parameters = null;

        $fh = \fopen($filename, 'r');

        while ($line = \fgets($fh)) {
            $line = \explode("\t", $line);

            if (\strpos($line[0], 'File format') === 0) {
                $columns = \explode(' ', $line[0]);
                $version = \array_pop($columns);

                if ($version < 4) {
                    throw new RuntimeException(
                        'Execution trace data file must be in format version 4 (or later)'
                    );
                }

                continue;
            }

            if (\count($line) > 9 && $line[5] == $unit && $parameters === null) {
                $parameters = \array_map('trim', \array_slice($line, 11, $line[10]));
                $level = $line[0];
            }

            if ($parameters !== null && $level == $line[0] && \count($line) == 6 && $line[2] == 'R') {
                $data[]     = \array_merge([\trim($line[5])], $parameters);
                $parameters = null;
            }
        }

        \fclose($fh);
		
		$data = \array_values(self::super_unique($data));
		
        return $data;
    }

    /**
     * See https://www.php.net/manual/function.array-unique.php#97285
     */
	protected static function super_unique(array $array): array
	{
		$result = \array_map("unserialize", \array_unique(\array_map("serialize", $array)));
	
	  	foreach ($result as $key => $value) {
			if ( is_array($value) ) {
		  		$result[$key] = super_unique($value);
			}
	  	}
	
	  	return $result;
	}
}
