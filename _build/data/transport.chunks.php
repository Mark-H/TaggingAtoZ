<?php
/**
 * SubscribeMe
 *
 * Copyright 2011 by Mark Hamstra <business@markhamstra.nl>
 *
 * This file is part of SubscribeMe, a subscriptions management extra for MODX Revolution
 *
 * SubscribeMe is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * SubscribeMe is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * SubscribeMe; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
*/

$ch = array(
    'atozTag' => 'Templates a tag.',
    'atozGroup' => 'Templates the AtoZ groups.',
    'atozOuter' => 'Wraps the entire AtoZ output.',
);

$idx = 0;

foreach ($ch as $sn => $sdesc) {
    $idx++;
    $ch[$idx] = $modx->newObject('modChunk');
    $ch[$idx]->fromArray(array(
       'id' => $idx,
       'name' => $sn,
       'description' => $sdesc . ' (Part of TaggingAtoZ)',
       'snippet' => getSnippetContent($sources['chunks'].$sn.'.chunk.tpl')
    ));
}
return $ch;

