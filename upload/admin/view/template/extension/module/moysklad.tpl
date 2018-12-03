<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-exchange" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-cogs"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">
        <div class="col-lg-12">
            <legend>Основные настройки</legend>
            <?php if(!empty($buttons)) { ?>
                <?php foreach($buttons as $button) { ?>            
                    <a class="<?= $button['class'] ?>"
                       href="<?php echo $button['href']; ?>"
                       title="<?php echo $button['title']; ?>"><?php echo $button['text']; ?></a>
                <?php } ?>
            <?php } ?>
        </div>
        <div class="col-lg-12">
            <legend>Отладка</legend>
            <?php if(!empty($debug_events)) { ?>
                <?php foreach($debug_events as $debug_event) { ?>
                    <?php echo $debug_event; ?>
                <?php } ?>
            <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>