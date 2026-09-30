<?php

/* original: https://github.com/munificent/jasic/blob/master/com/stuffwithstuff/Jasic.java */
error_reporting(E_ALL |  E_ERROR | E_WARNING | E_PARSE | E_COMPILE_ERROR);

class pBasic
{
    // token type enum
    const WORD = 0;
    const NUMBER = 1;
    const STRING = 2;
    const LABEL = 3;
    const LINE = 4;
    const EQUALS = 5;
    const OPERATOR = 6;
    const FNCTION = 7;
    const LEFT_PAREN = 8;
    const RIGHT_PAREN = 9;
    const SEPERATOR = 10;
    const EOF = 11;

    // tokenize state enum
    const STATE_DEFAULT = 10;
    const STATE_WORD = 11;
    const STATE_NUMBER = 12;
    const STATE_STRING = 13;
    const STATE_COMMENT = 14;

    public $variables;
    public $labels;
    public $currentStatement;
    public $outputBuffer;
    public $user_functions;
    public $functions;

    public function __construct()
    {
        $this->variables = array();
        $this->labels = array();
        $this->currentStatement = 0;
        $this->functions = array(
			'intval' => 'intval',
            'sqrt' => 'sqrt',
            'substr' => 'substr',
            'ord' => 'ord',
            'chr' => 'chr',
            'strlen' => 'strlen',
            'count' => 'count',
            'strtolower' => 'strtolower',
			'base64_encode' => 'base64_encode',
			'base64_decode' => 'base64_decode',
			'hexdec' => 'hexdec',
			'bindec' => 'bindec',
			'dechex' => 'dechex',
			'decbin' => 'decbin',
            'is_alnum' => 'ctype_alnum'
        );
        $this->user_functions = array();
        $this->outputBuffer = array();
    }

    private function tokenize($source)
    {
        $tokens = array();

        $token = '';

        $state = pBasic::STATE_DEFAULT;

        $charTokens = "\n=%+-*/<>,()";

        $functions = array_merge(array_keys($this->functions), array_keys($this->user_functions));

        $tokenTypes = array(pBasic::LINE, pBasic::EQUALS,
            pBasic::OPERATOR, pBasic::OPERATOR, pBasic::OPERATOR, pBasic::OPERATOR,
            pBasic::OPERATOR, pBasic::OPERATOR, pBasic::OPERATOR,
            pBasic::SEPERATOR, pBasic::LEFT_PAREN, pBasic::RIGHT_PAREN);

        for ($i = 0; $i < strlen($source); $i++)
        {
            $c = $source[$i];
            switch ($state)
            {
                case pBasic::STATE_DEFAULT:
                    if (strpos($charTokens,$c))
                    {
                        $tokens[] = new Token($c, $tokenTypes[strpos($charTokens,$c)]);
                    }
                    elseif (ctype_alpha($c) || strcmp($c,'_') == 0)
                    {
                        $token .= $c;
                        $state = pBasic::STATE_WORD;
                    }
                    elseif (ctype_digit($c))
                    {
                        $token .= $c;
                        $state = pBasic::STATE_NUMBER;
                    }
                    elseif (strcmp($c,'"') == 0)
                    {
                        $state = pBasic::STATE_STRING;
                    }
                    elseif (strcmp($c,'\'') == 0)
                    {
                        $state = pBasic::STATE_COMMENT;
                    }
                    break;

                case pBasic::STATE_WORD:
                    if (ctype_alnum($c) || strcmp($c,'_') == 0)
                    {
                        $token .= $c;
                    }
                    elseif (strcmp($c,':') == 0)
                    {
                        $tokens[] = new Token($token, pBasic::LABEL);
                        $token = '';
                        $state = pBasic::STATE_DEFAULT;
                    }
                    elseif (strcmp($c,'(') == 0 && in_array($token, $functions))
                    {
                        $tokens[] = new Token($token, pBasic::FNCTION);
                        $token = '';
                        $state = pBasic::STATE_DEFAULT;
                        $i--;
                    }
                    else
                    {
                        $tokens[] = new Token($token, pBasic::WORD);
                        $token = '';
                        $state = pBasic::STATE_DEFAULT;
                        $i--;
                    }
                    break;

                case pBasic::STATE_NUMBER:
                    if (ctype_digit($c))
                    {
                        $token .= $c;
                    }
                    else
                    {
                        $tokens[] = new Token($token, pBasic::NUMBER);
                        $token = '';
                        $state = pBasic::STATE_DEFAULT;
                        $i--;
                    }
                    break;

                case pBasic::STATE_STRING:
                    if (strcmp($c,'"') == 0)
                    {
                        $tokens[] = new Token($token, pBasic::STRING);
                        $token = '';
                        $state = pBasic::STATE_DEFAULT;
                    }
                    else
                    {
                        $token .= $c;
                    }
                    break;

                case pBasic::STATE_COMMENT:
                    if (strcmp($c,"\n") == 0)
                    {
                        $state = pBasic::STATE_DEFAULT;
                    }
                    break;
            }
        }

        return $tokens;

    }

    public function interpret($source, $args = array(), $environment = array())
    {

        $this->outputBuffer = array();
        //var_dump($args);
        $argcount = 0;
        foreach ($args as $argument)
        {
            $this->variables['arg'.strval($argcount)] = new UntypedValue($argument);
            $argcount++;
        }

        foreach ($environment as $key => $value)
        {
            $this->variables['_ENV_'.$key] = new UntypedValue($value);
        }

        //var_dump($this->variables);

        $tokens = $this->tokenize($source);

        //var_dump($tokens);

        $parser = new Parser($this, $tokens);

        //var_dump($this->labels);

        $statements = $parser->parse($this->labels);

        //var_dump($statements);

        //echo("DBG: beginning execution. currentStatement: ".$this->currentStatement."\n");

        while ($this->currentStatement < count($statements))
        {
            //echo("DBG: currentStatement: ".$this->currentStatement."\n");
            //var_dump($statements[$this->currentStatement]);
            $thisStatement = $this->currentStatement;
            $this->currentStatement++;
            //echo("DBG: increased currentStatement: ".$this->currentStatement."\n");
            $statements[$thisStatement]->execute($this);
            //echo("DBG: after execution currentStatement: ".$this->currentStatement."\n");
        }

        return $this->outputBuffer;
    }
}

class Parser
{
    public $interpreter;
    public $tokens;
    public $position;
    public function __construct($interpreter, $tokens)
    {
        $this->interpreter = $interpreter;
        $this->tokens = $tokens;
        $this->position = 0;
    }

    public function parse()
    {
        $statements = array();
        //echo("PARSER\n");
        while (true)
        {
            while ($this->matchType(pBasic::LINE));

            if ($this->matchType(pBasic::LABEL))
            {
                $label = $this->last(1)->text;
                //echo("Adding label: ".$label."\n");
                $this->interpreter->labels[$label] = count($statements);
            }
            elseif ($this->matchType(pBasic::FNCTION))
            {
                $params = array();
                $name = $this->last(1)->text;
                $this->consumeType(pBasic::LEFT_PAREN);

                if (!$this->matchType(pBasic::RIGHT_PAREN))
                {
                    $params[] = $this->expression();
                    while ($this->matchType(pBasic::SEPERATOR))
                    {
                        $params[] = $this->expression();
                    }
                    $this->consumeType(pBasic::RIGHT_PAREN);
                }

                //echo("Adding FunctionCallStatement: ".$name."\n");
                $statements[] = new FunctionCallStatement($name, $params);
            }
            elseif ($this->match(pBasic::WORD, pBasic::EQUALS))
            {
                $name = $this->last(2)->text;
                //echo("Adding AssignStatement: ".$name."\n");
                $value = $this->expression();
                $statements[] = new AssignStatement($name,$value);
            }
            elseif ($this->matchName('print'))
            {
                $statements[] = new PrintStatement($this->expression());
            }
            elseif ($this->matchName('println'))
            {
                //echo("Adding println\n");
                $statements[] = new PrintStatement($this->expression(),"\n");
            }
            elseif ($this->matchName('input'))
            {
                $statements[] = new InputStatement($this->consumeType(pBasic::WORD)->text);
            }
            elseif ($this->matchName('goto'))
            {
                //echo("Adding goto\n");
                $statements[] = new GotoStatement($this->consumeType(pBasic::WORD)->text);
            }
            elseif ($this->matchName('if'))
            {
                $condition = $this->expression();
                $this->consumeName('then');
                $label = $this->consumeType(pBasic::WORD)->text;
                $statements[] = new IfThenStatement($condition, $label);
            }
            else break;
        }
        return $statements;
    }


    private function expression()
    {
        return $this->operator();
    }

    private function operator()
    {
        $expression = $this->atomic();
        while ($this->matchType(pBasic::OPERATOR) || $this->matchType(pBasic::EQUALS))
        {
            $operator = $this->last(1)->text[0];
            $right = $this->atomic();
            $expression = new OperatorExpression($expression, $operator, $right);
        }
        return $expression;
    }


    private function atomic()
    {
        //echo("atomic: get(0): ".print_r($this->get(0),true));
        if ($this->matchType(pBasic::WORD))
        {
            //echo("Found Variable Expression\n");
            return new VariableExpression($this->last(1)->text);
        }
        elseif ($this->matchType(pBasic::FNCTION))
        {
            //echo("Found Function Expression\n");
            $params = array();
            $operator = $this->last(1)->text;
            $this->consumeType(pBasic::LEFT_PAREN);
            if (!$this->matchType(pBasic::RIGHT_PAREN))
            {
                $params[] = $this->expression();
                while ($this->matchType(pBasic::SEPERATOR))
                {
                    $params[] = $this->expression();
                }
                $this->consumeType(pBasic::RIGHT_PAREN);
            }
            return new FunctionExpression($operator, $params);
        }
        elseif ($this->matchType(pBasic::NUMBER))
        {
            //echo("Found Number\n");
            return new UntypedValue(floatval($this->last(1)->text));
        }
        elseif ($this->matchType(pBasic::STRING))
        {
            //echo("Found String Expression\n");
            return new UntypedValue($this->last(1)->text);
        }
        elseif ($this->matchType(pBasic::LEFT_PAREN))
        {
            //echo("Found Parentheses Expression\n");
            $expression = $this->expression();
            $this->consumeType(pBasic::RIGHT_PAREN);
            return $expression;
        }
        throw new ErrorException("Couldn't parse :( ");
    }

    private function match($typeOne, $typeTwo)
    {
        if ($this->get(0)->type != $typeOne) return false;
        if ($this->get(1)->type != $typeTwo) return false;
        $this->position += 2;
        return true;
    }

    private function matchName($name)
    {
        if ($this->get(0)->type != pBasic::WORD) return false;
        if (strcmp($this->get(0)->text,$name) != 0) return false;
        $this->position++;
        return true;
    }

    private function matchType($type)
    {
        if ($this->get(0)->type != $type) return false;
        $this->position++;
        return true;
    }

    private function consumeType($type)
    {
        if ($this->get(0)->type != $type) throw new ErrorException("Expected type ".$type.".");
        return $this->tokens[$this->position++];
    }

    private function consumeName($name)
    {
        if (!$this->matchName($name)) throw new ErrorException("Expected name ".$name.".");
        return $this->last(1);
    }

    private function last($offset)
    {
        return $this->tokens[$this->position - $offset];
    }

    private function get($offset)
    {
        if ($this->position + $offset >= count($this->tokens))
        {
            return new Token('', pBasic::EOF);
        }
        return $this->tokens[$this->position + $offset];
    }
}


interface Statement
{
    function execute($interpreter);
}

interface Expression
{
    function evaluate($interpreter);
}

interface Value extends Expression
{
    function toString();
    function toNumber();
}

class PrintStatement implements Statement
{
    public $expression;
    public $suffix;
    function __construct($expression, $suffix = '')
    {
        $this->expression = $expression;
        $this->suffix = $suffix;
    }
    public function execute($interpreter)
    {
        // echo($this->expression->evaluate($interpreter)->toString().$this->suffix);
        $interpreter->outputBuffer[] = $this->expression->evaluate($interpreter)->toString().$this->suffix;
    }
}

class InputStatement implements Statement
{
    public $name;
    function __construct($name)
    {
        $this->name = $name;
    }
    public function execute($interpreter)
    {
        try
        {
            //$input = readline(); // linux
            $input = stream_get_line(STDIN, 1024, PHP_EOL); // win
            $interpreter->variables[$this->name] = new UntypedValue($input);
        }
        catch (Exception $e)
        {

        }
    }
}

class FunctionCallStatement implements Statement
{
    public $functionExpression;
    function __construct($functioname, $parameterexpressions)
    {
        $this->functionExpression = new FunctionExpression($functioname, $parameterexpressions);
    }
    public function execute($interpreter)
    {
        $this->functionExpression->evaluate($interpreter);
    }
}

class AssignStatement implements Statement
{
    public $name;
    public $value;
    function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
    public function execute($interpreter)
    {
        $interpreter->variables[$this->name] = $this->value->evaluate($interpreter);
    }
}

class GotoStatement implements Statement
{
    public $label;
    function __construct($label)
    {
        $this->label = $label;
    }

    public function execute($interpreter)
    {
        //echo("DBG: pre goto currentStatement: ".$interpreter->currentStatement."\n");
        //var_dump($this->label);
        //var_dump($interpreter->labels);
        if (array_key_exists($this->label, $interpreter->labels))
        {
            $interpreter->currentStatement = intval($interpreter->labels[$this->label]);
            //echo("DBG: post goto currentStatement: ".$interpreter->currentStatement."\n");
        }
    }
}
class IfThenStatement implements Statement
{
    public $condition;
    public $label;
    function __construct($condition, $label)
    {
        $this->condition = $condition;
        $this->label = $label;
    }

    public function execute($interpreter)
    {
        if (array_key_exists($this->label, $interpreter->labels))
        {
            //echo("Evaluating ".print_r($this->condition,true));
            $value = $this->condition->evaluate($interpreter)->value;
            //echo(" to ".print_r($value,true)."\n");
            if ($value != 0)
            {
                //echo(" jumping\n");
                $interpreter->currentStatement = intval($interpreter->labels[$this->label]);
            }
        }
    }
}

class VariableExpression implements Expression
{
    public $name;
    function __construct($name)
    {
        $this->name = $name;
    }
    public function evaluate($interpreter)
    {
        if (array_key_exists($this->name,$interpreter->variables))
        {
            return $interpreter->variables[$this->name];
        }
        return new UntypedValue(0);
    }
}

class FunctionExpression implements Expression
{
    public $operator;
    public $parameterexpressions;
    public function __construct($operator, $parameterexpressions)
    {
        $this->operator = $operator;
        $this->parameterexpressions = $parameterexpressions;
    }
    public function evaluate($interpreter)
    {
        $parameters = array();
        foreach ($this->parameterexpressions as $expression)
        {
            $parameters[] = $expression->evaluate($interpreter)->value;
        }

        //echo(print_r($parameters,true));
		
        if (array_key_exists($this->operator, $interpreter->functions))
        {
			//echo($this->operator);
			$callresult = call_user_func_array($interpreter->functions[$this->operator],$parameters);
			//echo($callresult);
            return new UntypedValue($callresult);
        }
        elseif (array_key_exists($this->operator, $interpreter->user_functions))
        {
            $object = $interpreter->user_functions[$this->operator][0];
            $methodname = $interpreter->user_functions[$this->operator][1];

            //$interpreter->outputBuffer[] =  "Calling ".$methodname." with ".print_r($parameters, true);
            $callresult = call_user_func_array(array($object, $methodname), $parameters);
            $result = new UntypedValue($callresult);

            //$interpreter->outputBuffer[] =  "CallResult ";
            //var_dump($callresult);
            //$interpreter->outputBuffer[] =  "UntypedResult ".$result->toString();

            return $result;

        }

        throw new ErrorException("Unknown operator, ".$this->operator.".");
    }

}

class OperatorExpression implements Expression
{
    public $left;
    public $operator;
    public $right;
    public function __construct($left, $operator, $right)
    {
        $this->left = $left;
        $this->operator = $operator;
        $this->right = $right;
    }
    public function evaluate($interpreter)
    {
        $leftVal = $this->left->evaluate($interpreter);
        $rightVal = $this->right->evaluate($interpreter);

        switch ($this->operator)
        {
            case '=':
                if ($leftVal->isNumber())
                {
                    //echo("Doing = operator with numbers. ".$leftVal->toNumber().' == '.$rightVal->toNumber()."\n");
                    $comparison_result = new UntypedValue(($leftVal->toNumber() == $rightVal->toNumber()) ? 1 : 0);
                    //echo("Result: ".$comparison_result->toString()."\n");
                    return $comparison_result;
                }
                else
                {
                    //echo("Doing = operator with strings. ".$leftVal->toString().' == '.$rightVal->toString()."\n");
                    $comparison_result = new UntypedValue((strcmp($leftVal->toString(), $rightVal->toString()) == 0) ? 1 : 0);
                    //echo("Result: ".print_r($comparison_result,true)."\n");
                    return $comparison_result;
                }
            case '+':
                if ($leftVal->isString())
                {
                    //("Doing + operator with strings. ".$leftVal->toString().' + '.$rightVal->toString()."\n");
                    return new UntypedValue($leftVal->toString().$rightVal->toString());
                }
                else
                {
                    //echo("Doing + operator with numbers. ".$leftVal->toNumber().' + '.$rightVal->toNumber()."\n");
                    return new UntypedValue($leftVal->toNumber() + $rightVal->toNumber());
                }
            case '-':
                return new UntypedValue($leftVal->toNumber() - $rightVal->toNumber());
            case '*':
                return new UntypedValue($leftVal->toNumber() * $rightVal->toNumber());
            case '/':
                return new UntypedValue($leftVal->toNumber() / $rightVal->toNumber());
            case '%':
                return new UntypedValue($leftVal->toNumber() >= 0 ? $leftVal->toNumber() % $rightVal->toNumber() : $leftVal->toNumber() + $rightVal->toNumber());
            case '<':
                if ($leftVal->isString())
                {
                    return new UntypedValue((strcmp($leftVal->toString(), $rightVal->toString()) < 0) ? 1 : 0);
                }
                else
                {
                    return new UntypedValue(($leftVal->toNumber() < $rightVal->toNumber()) ? 1 : 0);
                }
            case '>':
                if ($leftVal->isString())
                {
                    return new UntypedValue((strcmp($leftVal->toString(), $rightVal->toString()) > 0) ? 1 : 0);
                }
                else
                {
                    return new UntypedValue(($leftVal->toNumber() > $rightVal->toNumber()) ? 1 : 0);
                }
        }
        throw new ErrorException("Unknown operator.");
    }
}

class UntypedValue implements Value
{
    public $value;
    public function __construct($value)
    {
        if (is_array($value) || is_string($value))
        {
            $this->value = $value;
        }
        else if (is_numeric($value) || is_bool($value))
        {
            $this->value = floatval($value);
        }
        else
        {
            $this->value = strval($value);
        }
    }

    public function isString()
    {
        return is_string($this->value);
    }

    public function isNumber()
    {
        return is_float($this->value);
    }

    public function isArray()
    {
        return is_array($this->value);
    }

    public function toString()
    {
        if ($this->isArray())
        {
            return implode(', ',$this->value);
        }
        return strval($this->value);
    }

    public function toNumber() { return floatval($this->value); }

    public function evaluate($interpreter) { return $this; }
}


class Token
{
    public $text;
    public $type;
    public function __construct($text, $type)
    {
        $this->text = $text;
        $this->type = $type;
    }
}

/*
if (count($argv) < 2)
{
    echo("Usage: pBasic [script]\n");
    echo("Usage: Where [script] is a relative path to a .jas script to run.");
    exit;
}

if (!file_exists($argv[1]))
{
    echo('Error: Given filename does not exist.');
    exit;
}

if (strstr($argv[1],'.ex'))
{
    $contents = gzuncompress(base64_decode(file_get_contents($argv[1])))."\n";
}
elseif (strstr($argv[1],'.pb'))
{
    $contents = file_get_contents($argv[1])."\n";
}


$pbasic = new pBasic();
echo(implode('', $pbasic->interpret($contents, array_slice($argv,1))));
*/
?>