<?php
$d1 = 5;
$d2 = 5;
$l4 = 5;
$l5 = $l5_2 = 5;
$l6[0] = 5;
$$l7 = 5;
list($l8) = [5];
list($l9, list($l9_2)) = [5, [5]];
$l10 || $l10_2 = 5;
if ($l11) { $l11_2 = 5; }
for ($l12 = 1; $l12 < 5; $l12++) { }
${$l13 = 'bar'} = 'baz';
${$l14 = ${$d1 + $l14_2 = 5}} = 5;
isset($l15) || $l15 = 5;
if (!isset($l16)) { $l16 = 5; }
function foo17($l17) { }
function foo18($l18 = 5) { }
function foo19($l19) { $l19; }
$l20 = function($l20_2) { $l20_2; };
$l21 = function($l21_2) use($d1) { return $l21_2 + $d1; };
function foo22() { $d1; }
function foo23() { global $d1; $d1; }
function foo24() { $l24 = 'd1'; global $$l24; $$l24 = 2; }
foreach ([5] as $l25) { $l25; }
foreach ([5 => 5] as $l26 => $l26_2) { $l26; }
if ($l27 = 5) { $l27; }
