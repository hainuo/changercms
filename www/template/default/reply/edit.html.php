<?php
/**
 * The edit reply view file of block module of chanzhiEPS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPLV12 (http://zpl.pub/page/zplv12.html)
 * @author      Chunsheng Wang <chunsheng@cnezsoft.com>
 * @package     block
 * @version     $Id$
 * @link        http://www.chanzhi.org
 */
?>
<?php include $this->loadModel('ui')->getEffectViewFile('default', 'common', 'header');?>
<?php include TPL_ROOT . 'common/kindeditor.html.php';?>
<?php $common->printPositionBar($board, $thread);?>

<div class='panel'>
  <div class='panel-heading'><strong><i class='icon-edit'></i> <?php echo $lang->reply->edit;?></strong></div>
  <div class='panel-body'>
    <form method='post' class='form-horizontal' id='editForm' enctype='multipart/form-data'>
      <div class='form-group'>
        <label class='col-md-1 col-sm-2 control-label'><?php echo $lang->reply->content;?></label>
        <div class='col-md-11 col-sm-10'><?php echo html::textarea('content', htmlspecialchars($reply->content), "rows='15' class='form-control'");?></div>
      </div>
      <div class='form-group'>
        <label class='col-md-1 col-sm-2 control-label'><?php echo $lang->thread->file;?></label>
        <div class='col-md-7 col-sm-8 col-xs-11'>
          <?php
          $this->reply->printFiles($reply, $canManage = true);
          echo $this->fetch('file', 'buildForm');
          ?>
        </div>
      </div>
      <?php if(zget($this->config->site, 'captcha', 'auto') == 'open'):?>
      <div class='form-group' id='captchaBox'><?php echo $this->loadModel('guarder')->create4thread();?></div>
      <?php else:?>
      <div class='form-group hiding' id='captchaBox'></div>
      <?php endif;?>
      <div class='form-group'>
        <label class='col-md-1 col-sm-2'></label>
        <div class='col-md-11 col-sm-10'><?php echo html::submitButton() . ' &nbsp; ' . html::backButton();?></div>
      </div>
    </form>
  </div>
</div>
<?php include $this->loadModel('ui')->getEffectViewFile('default', 'common', 'footer');?>
