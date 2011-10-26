<?php
/**
 * TaggingAtoZ, extended-ish from tagLister by Mark Hamstra (hello@markhamstra.com)
 *
 * tagLister
 *
 * Copyright 2010 by Shaun McCormick <shaun@modxcms.com>
 *
 * This file is part of tagLister, a simple tag listing snippet for MODx
 * Revolution.
 *
 * tagLister is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * tagLister is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * tagLister; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

/* Instantiate properties */
$path = $modx->getOption('taggingatoz.core_path',null,$modx->getOption('core_path').'components/taggingatoz/');
$defaults = include $path.'elements/snippets/taggingatoz.properties.php';
$scriptProperties = array_merge($defaults,$scriptProperties);
$debug = intval($scriptProperties['debug']);
$scriptProperties['groups'] = (!empty($scriptProperties['groups'])) ? explode(',',$scriptProperties['groups']) : array();

if ($debug) var_dump($scriptProperties);

$tvs = isset($scriptProperties['tvs']) ? explode(',',$scriptProperties['tvs']) : array();
if (count($tvs) < 1) return 'No TV specified.';

/* Parents support (from tagLister)*/
$parents = isset($scriptProperties['parents']) ? explode(',', $scriptProperties['parents']) : array();
$depth = isset($scriptProperties['depth']) ? (integer) $scriptProperties['depth'] : 10;
$children = array();
foreach ($parents as $parent) {
    $pchildren = $modx->getChildIds($parent, $depth);
    if (!empty($pchildren)) $children = array_merge($children, $pchildren);
}

if (!empty($children)) $parents = array_merge($parents, $children);

/* Get TV values (from tagLister, adjusted for multi TVs support) */
$c = $modx->newQuery('modTemplateVarResource');
$c->innerJoin('modTemplateVar','TemplateVar');
$c->innerJoin('modResource','Resource');
$c->leftJoin('modUser','CreatedBy','CreatedBy.id = Resource.createdby');
$c->leftJoin('modUser','PublishedBy','PublishedBy.id = Resource.publishedby');
$c->leftJoin('modUser','EditedBy','EditedBy.id = Resource.editedby');

if (!empty($parents)) {
    $c->where(array(
        'Resource.parent:IN' => $parents,
    ));
}

if (!$scriptProperties['includeDeleted']) {
    $c->where(array('Resource.deleted' => 0));
}
if (!$scriptProperties['includeUnpublished']) {
    $c->where(array('Resource.published' => 1));
}

$tvSearch = array();
foreach ($tvs as $tv) {
    $tvPk = (int)$tv;
    if (!empty($tvPk)) {
        $tvSearch[] = array('TemplateVar.id' => $tvPk);
    } else {
        $tvSearch[] = array('TemplateVar.name' => $tv);
    }
}

if (!empty($tvSearch)){
    $c->where($tvSearch,xPDOQuery::SQL_OR);
}

/* json where support */
if (!empty($scriptProperties['where'])) {
    $where = $modx->fromJSON($scriptProperties['where']);
    if (is_array($where) && !empty($where)) {
        $c->where($where);
    }
}

if ($debug) {
    $c->prepare();
    echo $c->toSQL();
}

$tags = $modx->getCollection('modTemplateVarResource',$c);

/* parse TV values */
$output = array();
$tagList = array();
/* @var modTemplateVarResource $tag */
foreach ($tags as $tag) {
    $v = $tag->get('value');
    $vs = explode('||',$v);
    $ar = array();
    foreach ($vs as $vs2) {
        $a = explode(',',$vs2);
        $ar = array_merge($ar,$a);
    }
    $vs = $ar;
    foreach ($vs as $key) {
        $key = trim($key);
        if (empty($key)) continue;
        if ($scriptProperties['toLower']) { /* allow for case-insensitive filtering */
            $key = $scriptProperties['useMultibyte'] ? mb_strtolower($key,$scriptProperties['encoding']) : strtolower($key);
        }

        /* increment tag count & group based on the first char. */
        $group = substr($key,0,1);
        if (is_numeric($group) && $scriptProperties['groupNumeric']) $group = $scriptProperties['numericHeader'];

        if (empty($tagList[$group][$key])) {
            $tagList[$group][$key] = 1;
        } else {
            $tagList[$group][$key]++;
        }
    }
}

ksort($tagList);

if ($debug) var_dump($tagList);

$numTags = count($tagList,1);
$output = array();
foreach ($tagList as $group => $groupTags) {
    if (!empty($scriptProperties['groups'])) {
        if (!in_array($group,$scriptProperties['groups'])) {
            continue;
        }
    }
    $groupOutput = array();
    $i = 0;
    foreach ($groupTags as $tag => $count) {

        if ($i >= $scriptProperties['limit']) break;
        $tagCls = $scriptProperties['cls'].((!empty($scriptProperties['altCls']) && $i % 2)? ' '.$scriptProperties['altCls'] : '');
        if (!empty($scriptProperties['firstCls']) && $i == 0) $tagCls .= ' '.$scriptProperties['firstCls'];
        if (!empty($scriptProperties['lastCls']) && ($i+1 >= $scriptProperties['limit'] || $i == $count)) $tagCls .= ' '.$scriptProperties['lastCls'];
        /* handle weighting for css */
        if (!empty($scriptProperties['weights']) && !empty($scriptProperties['weightCls'])) $tagCls .= ' '.$scriptProperties['weightCls'].ceil($count / (max($tagList) / $scriptProperties['weights']));

        $phs = array(
            'tag' => $tag,
            'tagVar' => $scriptProperties['tagVar'],
            'tagKey' => $scriptProperties['tvs'],
            'tagKeyVar' => $scriptProperties['tagKeyVar'],
            'count' => $count,
            'target' => $scriptProperties['target'],
            'cls' => $tagCls,
            'idx' => $i,
        );
        $groupOutput[] = $modx->getChunk($scriptProperties['tplTag'],$phs);
        $i++;
    }
    $phs = array(
        'group' => $group,
        'count' => count($groupTags),
        'wrapper' => implode($scriptProperties['tagSeparator'], $groupOutput),
    );
    $output[] = $modx->getChunk($scriptProperties['tplGroup'],$phs);
}

$phs = array(
    'countgroups' => count($output),
    'counttags' => $numTags,
    'wrapper' => implode($scriptProperties['groupSeparator'], $output),
);

$output = $modx->getChunk($scriptProperties['tplOuter'],$phs);

if (!empty($scriptProperties['toPlaceholder'])) {
    $modx->toPlaceholder($scriptProperties['toPlaceholder'],$output);
    return '';
}

return $output;


?>
