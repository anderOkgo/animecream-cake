<?php

// Brace should be on new line.
function myFunction() {
}

// Good.
function myFunction()
{
}

// Too many spaces.
function myFunction()   {
}

// Too many newlines.
function myFunction()

{
}

// Space before brace.
function myFunction()
 {
}

class myClass()
{
    // Brace should be on new line.
    function myFunction() {
    }
    
    // Good.
    function myFunction()
    {
    }

    // No aligned correctly.
    function myFunction()
{
}
    
    // Too many spaces.
    function myFunction()   {
    }
    
    // Too many newlines.
    function myFunction()

    {
    }
    
    // Space before brace.
    function myFunction()
     {
    }
}



/* Multi-line declarations */



// Brace should be on new line.
function myFunction($variable1, $variable2,
    $variable3, $variable4) {
}

// Good.
function myFunction($variable1, $variable2,
    $variable3, $variable4)
{
}

// Too many spaces.
function myFunction($variable1, $variable2,
    $variable3, $variable4)   {
}

// Too many newlines.
function myFunction($variable1, $variable2,
    $variable3, $variable4)

{
}

// Space before brace.
function myFunction($variable1, $variable2,
    $variable3, $variable4)
 {
}

class myClass()
{
    // Brace should be on new line.
    function myFunction($variable1, $variable2,
    $variable3, $variable4) {
    }
    
    // Good.
    function myFunction($variable1, $variable2,
    $variable3, $variable4)
    {
    }

    // No aligned correctly.
    function myFunction($variable1, $variable2,
    $variable3, $variable4)
{
}
    
    // Too many spaces.
    function myFunction($variable1, $variable2,
    $variable3, $variable4)   {
    }
    
    // Too many newlines.
    function myFunction($variable1, $variable2,
    $variable3, $variable4)

    {
    }
    
    // Space before brace.
    function myFunction($variable1, $variable2,
    $variable3, $variable4)
     {
    }
}

interface MyInterface()
{
    function myFunction();
}

function myFunction(
                    $arg1,
                    $arg2,
                    $arg3,
                    $arg4,
                    $arg5,
                    $arg6
                    )
{
}

function myFunction(
                    $arg1,
                    $arg2,
                    $arg3,
                    $arg4,
                    $arg5,
                    $arg6
                    ) {
}

function myFunction() {}
function myFunction()
{}

// phpcs:set Generic.Functions.OpeningFunctionBraceBsdAllman checkClosures 1

$closureWithArgs = function ($arg1, $arg2) {
    // body
};

$closureWithArgsAndVars = function ($arg1, $arg2) use ($var1, $var2) {
    // body
};

$test = function ($param) use ($result) {
    return null;
}

$test = function ($param) use ($result) : Something {
    return null;
};

// phpcs:set Generic.Functions.OpeningFunctionBraceBsdAllman checkClosures 0

$closureWithArgs = function ($arg1, $arg2) {
    // body
};

class Foo
{
//Comments should not affect code
    public function bar()
    {

    }
//Comments should not affect code
}

function myFunction() : Something {
    return null;
}
