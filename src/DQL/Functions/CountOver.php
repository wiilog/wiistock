<?php

namespace App\DQL\Functions;

use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class CountOver extends FunctionNode
{
    private Node $field;

    public function parse(Parser $parser): void {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->field = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string {
        return "COUNT({$this->field->dispatch($sqlWalker)}) OVER()";
    }
}
