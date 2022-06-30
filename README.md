# Archriss Content Parser #

## This extension parse the content to alter a tag and add usefull code ##

Wrap content with `<!--ARCPARSER_begin-->|<!--ARCPARSER_end-->` or `<!--FILEPARSER_begin-->|<!--FILEPARSER_end-->` and watch the result.

Link on internal / external / mail / file will be handled

For more information on handling watch Parser.php

## How it work

- Enable parsing in extension settings
- Wrap code with `<!--ARCPARSER_begin-->|<!--ARCPARSER_end-->`
- Wrap code with `<!--FILEPARSER_begin-->|<!--FILEPARSER_end-->` for only file extension + size
- !!! Do not Mix up parser `<!--ARCPARSER_begin--><!--FILEPARSER_begin-->|<!--FILEPARSER_end--><!--ARCPARSER_end-->`
- Clear cache
