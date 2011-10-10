<?php
/* @var modX $modx
 * @var array $scriptProperties
 */

/* Instantiate properties */
$tvs = $modx->getOption('tvs',$scriptProperties,null);
$tvs = isset($tvs) ? explode(',',$tvs) : array();
$target = (int)$modx->getOption('target',$scriptProperties,1);
$tagKey = $modx->getOption('tagKey',$scriptProperties,'tag');
$tagSeparator = $modx->getOption('tagSeparator',$scriptProperties,"\n");
$groupSeparator = $modx->getOption('groupSeparator',$scriptProperties,"\n");
$limit = $modx->getOption('limit',$scriptProperties,2);
$toLower = $modx->getOption('toLower',$scriptProperties,false);
$encoding = $modx->getOption('modx_charset',$scriptProperties,'UTF-8');
$useMultibyte = $modx->getOption('use_multibyte',$scriptProperties,false);
$tvDelimiter = $modx->getOption('tvDelimiter',$scriptProperties,',');
$groupNumeric = $modx->getOption('groupNumeric',$scriptProperties,true);
$numericHeader = $modx->getOption('numericHeader',$scriptProperties,'0-9');
$toPlaceholder = $modx->getOption('toPlaceholder',$scriptProperties,'');

$tplGroup = $modx->getOption('tplGroup',$scriptProperties,'atozGroupTpl');
$tplTag = $modx->getOption('tplTag',$scriptProperties,'atozTagTpl');
$tplOuter = $modx->getOption('tplOuter',$scriptProperties,'atozOuterTpl');

/* Parents support (from tagLister)*/
$parents = isset($parents) ? explode(',', $parents) : array();
$depth = isset($depth) ? (integer) $depth : 10;
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

if (!$modx->getOption('includeDeleted',$scriptProperties,false)) {
    $c->where(array('Resource.deleted' => 0));
}
if (!$modx->getOption('includeUnpublished',$scriptProperties,false)) {
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
$where = $modx->getOption('where',$scriptProperties,'');
if (!empty($where)) {
    $where = $modx->fromJSON($where);
    if (is_array($where) && !empty($where)) {
        $c->where($where);
    }
}

$tags = $modx->getCollection('modTemplateVarResource',$c);

/* parse TV values */
$output = array();
$tagList = array();
/* @var modTemplateVarResource $tag */
foreach ($tags as $tag) {
    $v = $tag->get('value');
    $vs = explode($tvDelimiter,$v);
    foreach ($vs as $key) {
        $key = trim($key);
        if (empty($key)) continue;
        if ($toLower) { /* allow for case-insensitive filtering */
            $key = $useMultibyte ? mb_strtolower($key,$encoding) : strtolower($key);
        }

        /* increment tag count & group based on the first char. */
        $group = substr($key,0,1);
        if (is_numeric($group) && $groupNumeric) $group = $numericHeader;

        if (empty($tagList[$group][$key])) {
            $tagList[$group][$key] = 1;
        } else {
            $tagList[$group][$key]++;
        }
    }
}

ksort($tagList);

$numTags = count($tagList,1);

$output = array();
foreach ($tagList as $group => $groupTags) {
    $groupOutput = array();
    $tagIdx = 0;
    foreach ($groupTags as $tag => $count) {
        $tagIdx++;
        $phs = array(
            'idx' => $tagIdx,
            'count' => $count,
            'tag' => $tag,
            'link' => $modx->makeUrl($target,'',array($tagKey => $tag)),
        );
        $groupOutput[] = $modx->getChunk($tplTag,$phs);
    }
    $phs = array(
        'group' => $group,
        'count' => count($groupOutput),
        'wrapper' => implode($tagSeparator, $groupOutput),
    );
    $output[] = $modx->getChunk($tplGroup,$phs);
}

$phs = array(
    'countgroups' => count($output),
    'counttags' => $numTags,
    'wrapper' => implode($groupSeparator, $output),
);

$output = $modx->getChunk($tplOuter,$phs);

if (!empty($toPlaceholder)) {
    $modx->toPlaceholder($toPlaceholder,$output);
    return '';
}
return $output;




?>