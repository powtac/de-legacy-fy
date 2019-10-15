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

class CharacterizationTestGenerator
{
    /**
     * @var string
     */
    private static $classTemplate = '<?php
use PHPUnit\Framework\TestCase;

class %s extends TestCase
{

%s
    protected function setUp(): void 
    {
        %s
    }

%s

    /**
     * @param string $serializedValue
     *
     * @return mixed
     */
    private function decode($serializedValue)
    {
        return unserialize(base64_decode($serializedValue));
    }
}
';
	
	private static $providerMethodTemplate = '
    /**
     * @return array
     */
    public function %sProvider()
    {
        return [
%s
        ];
    }
';

	private static $testMethodTemplate = '
    /**
     * @return array
     * @dataProvider %sProvider
     */
    public function test%s()
    {
        $args     = func_get_args();
        $expected = array_shift($args);
        $this->assertEquals($expected, %s);
    }
';

    /**
     * @param string $traceFile
     * @param string $unit
     * @param string $testClass
     * @param string $testFile
     */
    public function generate($traceFile, $units, $testClass, $testFile)
    {
    	$methodBuffer = '';
        $parser = new XdebugTraceParser;
		$units	= explode(',', $units);
		$units	= array_filter($units, 'trim');
		foreach ($units as $unit) {
	        $data   = $parser->parse($traceFile, $unit);
	        $buffer = '';
	
	
	        for ($i = 0; $i < \count($data); $i++) {
	            $last = $i == \count($data) - 1;
	
	            $buffer .= \sprintf(
	                '            [%s]%s',
	                \implode(
	                    ', ',
	                    \array_map(
	                        function ($parameter) {
	                            return '$this->decode(\'' . $parameter . '\')';
	                        },
	                        $data[$i]
	                    )
	                ),
	                !$last ? ",\n" : ''
	            );
	        }
	        
	        $unitType = self::unittypeDetector($unit);
			$classes = array();
	        switch($unitType['type']) {
	        	case 'function':
	        		$caller = 'call_user_func_array(\''.$unitType['functionname'].'\', $args)';
	        	break;
	        	
	        	case 'class':
	        		$caller = 'call_user_func_array(array($this->'.$unitType['classname'].', \''.$unitType['method'].'\'), $args)';
					
					$classes[] = $unitType['classname'];
	        	break;
	        	
	        	case 'static':
	        		$caller = 'call_user_func_array(\''.$unitType['classname'].'::'.$unitType['method'].'\', $args)';
	        	break;
	        }

			$params = '';
			$setupCode = '';
			foreach ($classes as $class) {
				$params 	.= "    ".'protected $'.$class.';'."\n";
				$setupCode 	.= '$this->'.$class.' = new '.$class.';'."\n";
			}

	        $methodBuffer .= \sprintf(
	            self::$testMethodTemplate,
				$unitType['clean'],
	            ucfirst($unitType['clean']),
	            $caller
	        );
	        
	        $methodBuffer .= \sprintf(
	            self::$providerMethodTemplate,
	            $unitType['clean'],
	            $buffer
	        );
		}

        \file_put_contents(
            $testFile,
            \sprintf(
                self::$classTemplate,
                $testClass,
				$params,
				$setupCode,
                $methodBuffer
            )
        );
    }
    
    private static function unittypeDetector(string $unit) {
    	if (strpos($unit, '->') !== false) {
    		return array('type' => 'class', 'classname' => explode('->', $unit)[0], 'method' => explode('->', $unit)[1], 'clean' => explode('->', $unit)[0].ucfirst(explode('->', $unit)[1]));
    	}
    	
    	if (strpos($unit, '::') !== false) {
    		return array('type' => 'static', 'classname' => explode('::', $unit)[0], 'method' => explode('::', $unit)[1], 'clean' => explode('::', $unit)[0].ucfirst(explode('::', $unit)[1]));
    	}
    	
    	return array('type' => 'function', 'functionname' => $unit, 'clean' => $unit);
    }
}




