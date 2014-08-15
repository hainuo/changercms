<?php
/**
 * The model file of block module of chanzhiEPS.
 *
 * @copyright   Copyright 2013-2013 青岛息壤网络信息有限公司 (QingDao XiRang Network Infomation Co,LTD www.xirangit.com)
 * @license     http://api.chanzhi.org/goto.php?item=license
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     block
 * @version     $ID$
 * @link        http://www.chanzhi.org
 */
class blockModel extends model
{
    /**
     * Get block by id.
     * 
     * @param string $blockID 
     * @access public
     * @return object   the block.
     */
    public function getByID($blockID)
    {
        $block = $this->dao->findByID($blockID)->from(TABLE_BLOCK)->fetch();
        if(strpos($block->type, 'code') === false) $block->content = json_decode($block->content);
        if(empty($block->content)) $block->content = new stdclass();
        return $block;
    }

    /**
     * Get block list of one site.
     * 
     * @param  string    $template
     * @param  object    $pager 
     * @access public
     * @return array
     */
    public function getList($template, $pager)
    {
        return $this->dao->select('*')->from(TABLE_BLOCK)->where('template')->eq($template)->orderBy('id_desc')->page($pager)->fetchAll('id');
    }

    /**
     * Get block list of one region.
     * 
     * @param  string    $module 
     * @param  string    $method 
     * @access public
     * @return array
     */
    public function getPageBlocks($module, $method)
    {
        $pages      = "all,{$module}_{$method}";
        $rawLayouts = $this->dao->select('*')->from(TABLE_LAYOUT)
            ->where('page')->in($pages)
            ->andWhere('template')->eq(!empty($this->config->site->template) ? $this->config->site->template : 'default')
            ->fetchGroup('page', 'region');

        $blocks = array();
        foreach($rawLayouts as $page => $pageBlocks)
        {
            foreach($pageBlocks as $regionBlocks) 
            {
                $regionBlocks = json_decode($regionBlocks->blocks);
                if(empty($regionBlocks)) continue;
                foreach($regionBlocks as $block) $blocks[] = $block->id;
            }
        }

        $blocks = $this->dao->select('*')->from(TABLE_BLOCK)->where('id')->in($blocks)->fetchAll('id');

        $layouts = array();
        foreach($rawLayouts as $page => $pageBlocks) 
        {
            $layouts[$page] = array();
            foreach($pageBlocks as $region => $regionBlock)
            {
                $layouts[$page][$region] = array();

                $regionBlocks =  json_decode($regionBlock->blocks);

                foreach($regionBlocks as $block)
                {
                    if(isset($blocks[$block->id])) 
                    {
                        $mergedBlock = $blocks[$block->id];
                        if(isset($block->grid))       $mergedBlock->grid       = $block->grid;
                        if(isset($block->titleless))  $mergedBlock->titleless  = $block->titleless;
                        if(isset($block->borderless)) $mergedBlock->borderless = $block->borderless;
                        $layouts[$page][$region][] = $mergedBlock;
                    }
                }
            }
        }
        return $layouts;
    }

    /**
     * Get block list of one region.
     * 
     * @param  string    $page 
     * @param  string    $region 
     * @param  string    $template 
     * @access public
     * @return array
     */
    public function getRegionBlocks($page, $region, $template)
    {
        $regionBlocks = $this->dao->select('*')->from(TABLE_LAYOUT)->where('page')->eq($page)->andWhere('region')->eq($region)->andWhere('template')->eq($template)->fetch('blocks');
        $regionBlocks = json_decode($regionBlocks);
        if(empty($regionBlocks)) return array();

        $blockIdList = array();
        foreach($regionBlocks as $block) $blockIdList[] = $block->id;

        $blocks = $this->dao->select('*')->from(TABLE_BLOCK)->where('id')->in($blockIdList)->fetchAll('id');

        $sortedBlocks = array();
        foreach($regionBlocks as $block) 
        {
            if(!isset($blocks[$block->id])) continue;

            $sortedBlocks[$block->id] = $blocks[$block->id];
            $sortedBlocks[$block->id]->grid       = $block->grid;
            $sortedBlocks[$block->id]->titleless  = $block->titleless;
            $sortedBlocks[$block->id]->borderless = $block->borderless;
        }
        return $sortedBlocks;
    }

    /**
     * Get block id => title pairs.
     * 
     * @access public
     * @return array
     */
    public function getPairs($template)
    {
        return $this->dao->select('id, title')->from(TABLE_BLOCK)->where('template')->eq($template)->fetchPairs();
    }
    
    /**
     * Create type  select area.
     * 
     * @param  string    $template
     * @param  string    $type 
     * @param  int       $blockID 
     * @access public
     * @return string
     */
    public function createTypeSelector($template, $type, $blockID = 0)
    {
        $select = "<div class='btn-group'><button type='button' class='btn btn-default dropdown-toggle' data-toggle='dropdown'>";
        $select .= $this->lang->block->$template->typeList[$type] . " <span class='caret'></span></button>";
        $select .= "<ul class='dropdown-menu' role='menu'>";
        foreach($this->lang->block->$template->typeGroups as $block => $group)
        {
            if(isset($lastGroup) and $group !== $lastGroup) $select .= "<li class='divider'></li>";
            $lastGroup = $group;
            $class = ($block == $type) ? "class='active'" : '';
            if($blockID)
            {
                $select .= "<li {$class}>" . html::a(helper::createLink('block', $this->app->getMethodName(), "template={$template}&blockID={$blockID}&type={$block}"), $this->lang->block->$template->typeList[$block]) . "</li>";
            }
            else
            {
                $select .= "<li {$class}>" . html::a(helper::createLink('block', $this->app->getMethodName(), "template={$template}&type={$block}"), $this->lang->block->$template->typeList[$block]) . "</li>";
            }
        }
        $select .= "</ul></div>" .  html::hidden('type', $type);
        return $select;
    }

    /**
     * Create form entry of one block backend.
     * 
     * @param  string  $template
     * @param  object  $block 
     * @param  mix     $key 
     * @access public
     * @return void
     */
    public function createEntry($template, $block = null, $key)
    {
        $blockOptions[''] = $this->lang->block->select;
        $blockOptions += $this->getPairs($template);
        $blockID = isset($block->id) ? $block->id : '';
        $type    = isset($block->type) ? $block->type : '';
        $grid    = isset($block->grid) ? $block->grid : '';

        $entry = "<div class='block-item row' data-id='" . $blockID . "'>";
        $entry .= "<div class='col-xs-8'><div class='input-group'>";
        $entry .= html::select("blocks[{$key}]", $blockOptions, $blockID, "class='form-control'");

        $titlelessChecked = isset($block->titleless) && $block->titleless ? 'checked' : '';
        $borderlessChecked = isset($block->borderless) && $block->borderless ? 'checked' : '';
        $entry .= "
            <div class='input-group-addon'>
              <div class='checkbox checkbox-inline'>
                 <label>
                   <input type='checkbox' {$titlelessChecked} value='1'><input type='hidden' name='titleless[{$key}]' /><span>{$this->lang->block->titleless}</span>
                 </label>
              </div>
              <div class='checkbox checkbox-inline'>
                <label>
                  <input type='checkbox' {$borderlessChecked} value='1'><input type='hidden' name='borderless[{$key}]' /><span>{$this->lang->block->borderless}</span>
                </label>
              </div>
            </div></div></div>";

        $entry .= "<div class='col-xs-2'>";
        $entry .= html::select("grid[{$key}]", $this->lang->block->gridOptions, $grid, "class='form-control'");
        $entry .= '</div>';

        $entry .= '<div class="text-center col-xs-2 actions">';
        $entry .= html::a('javascript:;', "<i class='icon-plus'></i>", "class='plus'");
        $entry .= html::a('javascript:;', "<i class='icon-remove'></i>", "class='delete'");
        $entry .= html::a(inlink('edit', "template={$template}&blockID={$blockID}&type={$type}"), "<i class='icon-edit'></i>", "class='edit'");
        $entry .= "<i class='icon-move sort-handle'></i>";
        $entry .= '</div></div>';
        return $entry;
    }

    /**
     * Create a block.
     * 
     * @param  string  $template
     * @access public
     * @return void
     */
    public function create($template)
    {
        $block = fixer::input('post')->add('template', $template)->stripTags('content', $this->config->block->allowedTags)->get();
        if($this->post->type == 'phpcode') $block = fixer::input('post')->add('template', $template)->get();

        $gpcOn = version_compare(phpversion(), '5.4', '<') and get_magic_quotes_gpc();

        if(isset($block->params))
        {
            foreach($block->params as $field => $value)
            {
                if(is_array($value)) $block->params[$field] = join($value, ',');
            }
            if($this->post->content) $block->params['content'] = $gpcOn ? stripslashes($block->content) : $block->content;
            $block->content = helper::jsonEncode($block->params);
        }

        $this->dao->insert(TABLE_BLOCK)->data($block, 'params,uid')->batchCheck($this->config->block->require->create, 'notempty')->autoCheck()->exec();

        $blockID = $this->dao->lastInsertID();
        $this->loadModel('file')->updateObjectID($this->post->uid, $blockID, 'block');

        return true;
    }

    /**
     * Update  block.
     * 
     * @param  string  $template
     * @access public
     * @return void
     */
    public function update($template)
    {
        $block = fixer::input('post')->add('template', $template)->stripTags('content', $this->config->block->allowedTags)->get();
        if($this->post->type == 'phpcode') $block = fixer::input('post')->add('template', $template)->get();

        $gpcOn = version_compare(phpversion(), '5.4', '<') and get_magic_quotes_gpc();

        if(isset($block->params))
        {
            foreach($block->params as $field => $value)
            {
                if(is_array($value)) $block->params[$field] = join($value, ',');
            }
            if($this->post->content) $block->params['content'] = $gpcOn ? stripslashes($block->content) : $block->content;
            $block->content = helper::jsonEncode($block->params);
        }

        $this->dao->update(TABLE_BLOCK)->data($block, 'params,uid,blockID')
            ->batchCheck($this->config->block->require->edit, 'notempty')
            ->autoCheck()
            ->where('id')->eq($this->post->blockID)
            ->exec();

        $this->loadModel('file')->updateObjectID($this->post->uid, $this->post->blockID, 'block');
        return true;
    }

    /**
     * Delete one block.
     * 
     * @param  int    $blockID 
     * @param  null    $table 
     * @access public
     * @return void
     */
    public function delete($blockID, $table = null)
    {
        $this->dao->delete()->from(TABLE_BLOCK)->where('id')->eq($blockID)->exec();
        return !dao::isError();
    }

    /**
     * Set block of one region.
     * 
     * @param string $page 
     * @param string $region 
     * @param string $template 
     * @access public
     * @return void
     */
    public function setRegion($page, $region, $template)
    {
        $layout = new stdclass();
        $layout->page   = $page;
        $layout->region = $region;
        $layout->template = $template;

        if(!$this->post->blocks)
        {
            $this->dao->delete()->from(TABLE_LAYOUT)->where('page')->eq($page)->andWhere('region')->eq($region)->andWhere('template')->eq($template)->exec();
            if(!dao::isError()) return true;
        }

        $blocks = array();
        foreach($this->post->blocks as $key => $block)
        {
            $blocks[$key]['id']         = $block;
            $blocks[$key]['grid']       = $this->post->grid[$key];
            $blocks[$key]['titleless']  = $this->post->titleless[$key];
            $blocks[$key]['borderless'] = $this->post->borderless[$key];
        }

        /* Resort blocks. */
        foreach($blocks as $block) $sortedBlocks[] = $block;
        $layout->blocks = helper::jsonEncode($sortedBlocks);

        $count = $this->dao->select('count(*) as count')->from(TABLE_LAYOUT)->where('page')->eq($page)->andWhere('region')->eq($region)->andWhere('template')->eq($template)->fetch('count');

        if($count)  $this->dao->update(TABLE_LAYOUT)->data($layout)->where('page')->eq($page)->andWhere('region')->eq($region)->andWhere('template')->eq($template)->exec();
        if(!$count) $this->dao->insert(TABLE_LAYOUT)->data($layout)->exec();

        return !dao::isError();
    }

    /**
     * Print blocks of one region.
     * 
     * @param  array    $blocks 
     * @param  string   $method 
     * @param  string   $region 
     * @param  string   $containerHeader 
     * @param  string   $containerFooter 
     * @access public
     * @return void
     */
    public function printRegion($blocks, $method = '', $region = '', $withGrid = false, $containerHeader = '', $containerFooter = '')
    {
        if(!isset($blocks[$method][$region])) return '';
        $blocks = $blocks[$method][$region];
        $html   = '';
        foreach($blocks as $block) $html .= $this->parseBlockContent($block, $withGrid, $containerHeader, $containerFooter);
        echo $html;
    }

    /**
     * Parse the content of one block.
     * 
     * @param  object    $block 
     * @param  bool      $withGrid          
     * @param  string    $containerHeader 
     * @param  string    $containerFooter 
     * @access private
     * @return string
     */
    private function parseBlockContent($block, $withGrid = false, $containerHeader, $containerFooter)
    {
        $withGrid = $withGrid and isset($block->grid);
        if($withGrid)
        {
            if($block->grid == 0) echo "<div class='col-md-4 col-auto'>";
            else echo "<div class='col-md-{$block->grid}' data-grid='{$block->grid}'>";
        }

        $tplPath = $this->app->getTplRoot() . $this->config->site->template . DS . 'view' . DS . 'block' . DS;

        /* First try block/ext/sitecode/view/block/ */
        $extBlockRoot = $tplPath . "/ext/_{$this->config->site->code}/";
        $blockFile    = $extBlockRoot . strtolower($block->type) . '.html.php';

        /* Then try block/ext/view/block/ */
        if(!file_exists($blockFile))
        {
            $extBlockRoot = $tplPath . 'ext' . DS;
            $blockFile    = $extBlockRoot . strtolower($block->type) . '.html.php';

            /* No ext file, use the block/view/block/. */
            if(!file_exists($blockFile))
            {
                $blockFile = $tplPath . strtolower($block->type) . '.html.php';
                if(!file_exists($blockFile)) return '';
            }
        }

        $blockClass = '';
        if(isset($block->borderless) and $block->borderless) $blockClass .= 'panel-borderless';
        if(isset($block->titleless) and $block->titleless) $blockClass  .= ' panel-titleless';

        $content = is_object($block->content) ? $block->content : json_decode($block->content);

        if(isset($this->config->block->defaultIcons[$block->type])) 
        {
            $defaultIcon = $this->config->block->defaultIcons[$block->type];
            $iconClass   = isset($content->icon) ? $content->icon : $defaultIcon;
            $icon        = $iconClass ? "<i class='{$iconClass}'></i> " : "" ;
        }

        $color  = '<style>';
        $color .= '#block' . $block->id . '{';
        $color .= isset($content->backgroundColor) ? 'background-color:' . $content->backgroundColor . ';' : '';
        $color .= isset($content->textColor) ? 'color:' . $content->textColor . ';' : '';
        $color .= isset($content->borderColor) ? 'border-color:' . $content->borderColor . ';' : '';
        $color .= '}';
        $color .= '#block' . $block->id . ' .panel-heading{';
        $color .= isset($content->titleColor) ? 'color:' .$content->titleColor . ';' : '';
        $color .= isset($content->titleBackground) ? 'background:' .$content->titleBackground . ';' : '';
        $color .= '}';
        $color .= isset($content->iconColor) ? '#block' . $block->id . ' i{color:' .$content->iconColor . ';}' : '';
        $color .= isset($content->linkColor) ? '#block' . $block->id . ' a{color:' .$content->linkColor . ';}' : '';
        $color .= '</style>';

        echo $containerHeader;
        echo $color;
        include $blockFile;
        echo $containerFooter;

        if($withGrid) echo '</div>';
    }

    /**
     * Whether can create phpcode block or not.
     * 
     * @access public
     * @return array
     */
    public function canCreatePHP()
    {
        $okFile = dirname($this->app->getDataRoot()) . DS . 'ok';
        if(!file_exists($okFile) or time() - filemtime($okFile) > 3600)
        {
            return array('result' => 'fail', 'okFile' => $okFile);
        }

        return array('result' => 'success');
    }

    /**
     * Load language from a template.
     * 
     * @param  string $template
     * @access public
     * @return void
     */
    public function loadTemplateLang($template)
    {
        $this->config->site->template = $template;
        $this->app->loadLang('block');
    }
}
