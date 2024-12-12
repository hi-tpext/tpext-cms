<?php

namespace tpext\cms\admin\controller;

use think\Controller;
use tpext\manager\common\logic\DbLogic;
use tpext\builder\traits\actions\HasBase;
use tpext\builder\traits\actions\HasIndex;
use tpext\cms\common\Module;

/**
 * Undocumented class
 * @title 数据表管理
 */
class Cmscontentfields extends Controller
{
    use HasBase;
    use HasIndex;

    /**
     * Undocumented variable
     *
     * @var DbLogic
     */
    protected $dbLogic;

    protected $tableName = '';

    protected function initialize()
    {
        $this->pageTitle = '字段管理';
        $this->indexText = '内容字段列表';

        $this->pk = 'COLUMN_NAME';
        $this->dbLogic = new DbLogic;
        $this->tableName = $this->dbLogic->getPrefix() . 'cms_content';
        $this->pagesize = 9999; //不产生分页
    }

    /**
     * 生成数据，如数据不是从`$this->dataModel`得来时，可重写此方法
     * 比如使用db()助手方法、多表join、或以一个自定义数组为数据源
     *
     * @param array $where
     * @param string $sortOrder
     * @param integer $page
     * @param integer $total
     * @return array|\think\Collection|\Generator
     */
    protected function buildDataList($where = [], $sortOrder = '', $page = 1, &$total = -1)
    {
        $name = $this->tableName;

        $data = $this->dbLogic->getFields($name, 'COLUMN_NAME,COLUMN_TYPE,COLUMN_DEFAULT,COLUMN_COMMENT,IS_NULLABLE,NUMERIC_SCALE,NUMERIC_PRECISION,CHARACTER_MAXIMUM_LENGTH,DATA_TYPE');

        $total = count($data);

        foreach ($data as &$field) {
            if ($this->dbLogic->isInteger($field['DATA_TYPE']) || $this->dbLogic->isDecimal($field['DATA_TYPE']) || $this->dbLogic->isChartext($field['DATA_TYPE'])) {
                $field['LENGTH'] = preg_replace('/^\w+\((\d+).+?$/', '$1', $field['COLUMN_TYPE']);
            }

            $keys = $this->dbLogic->getKeys($name, $field['COLUMN_NAME']);

            $field['ATTR'] = '';

            $ATTR = [];

            if (strpos($field['COLUMN_TYPE'], 'unsigned')) {
                $ATTR['unsigned'] = 'unsigned';
            }

            foreach ($keys as $key) {
                if (strtoupper($key['INDEX_NAME']) == 'PRIMARY') {
                    $field['__can_delete__'] = 0;
                    $ATTR['index'] = 'index';
                    continue;
                }

                if ($key['NON_UNIQUE'] == 1) {
                    $ATTR['index'] = 'index';
                } else {
                    $ATTR['unique'] = 'unique';
                }
            }

            $field['ATTR'] = implode(',', $ATTR);
            $field['DATA_TYPE'] = strtolower($field['DATA_TYPE']);
            $field['IS_NULLABLE'] = $field['IS_NULLABLE'] == 'YES';

            if (is_null($field['COLUMN_DEFAULT'])) {
                $field['COLUMN_DEFAULT'] = 'NULL';
            } else {
                $field['COLUMN_DEFAULT'] = trim($field['COLUMN_DEFAULT'], "'");
            }
        }

        unset($keys, $field);

        return $data;
    }

    /**
     * 构建表格
     *
     * @return void
     */
    protected function buildTable(&$data = [], $isExporting = false)
    {
        $table = $this->table;
        $protectedFields = $this->getProtectedFields();

        $table->show('COLUMN_NAME', '字段名');
        $table->show('COLUMN_COMMENT', '字段注释');
        $table->match('DATA_TYPE', '数据类型')->options($this->dbLogic::$FIELD_TYPES);
        $table->show('LENGTH', '长度')->default('-');
        $table->show('NUMERIC_SCALE', '小数点')->default('-');
        $table->show('COLUMN_DEFAULT', '默认值');
        $table->match('IS_NULLABLE', '可为NULL')->yesOrNo();
        $table->matches('ATTR', '属性')->options(['index' => '索引', 'unique' => '唯一', 'unsigned' => '非负']);

        $table->getToolbar()
            ->btnAdd()
            ->btnLink(url('trash'), '字段回收站', 'btn-danger', 'mdi-delete-variant');

        $table->getActionbar()
            ->btnEdit()
            ->btnDelete()
            ->mapClass([
                'edit' => [
                    'disabled' => function ($row) use ($protectedFields) {
                        return in_array($row['COLUMN_NAME'], $protectedFields);
                    }
                ],
                'delete' => [
                    'disabled' => function ($row) use ($protectedFields) {
                        return in_array($row['COLUMN_NAME'], $protectedFields);
                    }
                ]
            ]);

        $table->useCheckbox(false);
    }

    /**
     * Undocumented function
     * @title 回收站
     *
     * @return mixed
     */
    public function trash()
    {
        $builder = $this->builder($this->pageTitle, '回收站');
        $table = $builder->table();

        $table->match('type', '类型')->options(['table' => '表', 'field' => '字段'])->mapClassGroup([['table', 'success'], ['field', 'info']]);
        $table->raw('name', '名称');
        $table->show('comment', '注释');
        $table->show('delete_time', '删除时间')->getWrapper()->addStyle('width:180px');

        $data = [];

        $deletedFields = $this->dbLogic->getDeletedFields($this->tableName);

        foreach ($deletedFields as $field) {
            $arr = explode('_del_at_', $field['COLUMN_NAME']);
            $data[] = [
                'id' => $field['COLUMN_NAME'],
                'name' => $this->tableName . '.' . $field['COLUMN_NAME'],
                'comment' => $field['COLUMN_COMMENT'],
                'type' => 'field',
                'delete_time' => date('Y-m-d H:i:s', $arr[1]),
            ];
        }

        $table->fill($data);

        $table->getActionbar()
            ->btnDelete(url('destroy'), '删除', 'btn-danger', 'mdi-delete', 'title="彻底删除表或字段"', '删除后数据不可恢复，确定要执行此操作吗？')
            ->btnPostRowid('recovery', url('recovery'), '恢复', 'btn-success', 'mdi-backup-restore', 'title="恢复表或字段"');

        $table->useCheckbox(false);
        $table->useToolbar(false);

        if (request()->isAjax()) {
            return $table->partial()->render();
        }

        return $builder->render();
    }

    /**
     * Undocumented function
     * @title 恢复已删除的表或字段
     *
     * @return mixed
     */
    public function recovery()
    {
        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;
        foreach ($ids as $id) {
            if ($this->dbLogic->recoveryField($this->tableName, $id)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功恢复', '', ['script' => '<script>parent.$(".search-refresh").trigger("click");</script>']);
        } else {
            $this->error('恢复失败' . $this->dbLogic->getErrorsText());
        }
    }

    /**
     * Undocumented function
     * @title 彻底删除表或字段
     * @return mixed
     */
    public function destroy()
    {
        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;
        foreach ($ids as $id) {
            if ($this->dbLogic->dropField($this->tableName, $id)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功删除');
        } else {
            $this->error('删除失败' . $this->dbLogic->getErrorsText());
        }
    }

    /**
     * 构建表单
     *
     * @param boolean $isEdit
     * @param array $data
     */
    protected function buildForm($isEdit, &$data = [])
    {
        $form = $this->form;

        $form->text('COLUMN_NAME', '字段名')->help('英文字母或数字下或划线组成')->required();
        $form->text('COLUMN_COMMENT', '字段注释')->required();
        $type = $form->select('DATA_TYPE', '数据类型')->options($this->dbLogic::$FIELD_TYPES)->required()
            ->when(['date', 'datetime', 'timestamp'])->with(
                $form->switchBtn('IS_NULLABLE', '可为NULL')
            );
        $form->number('LENGTH', '长度')->help('文本或数值类型才有长度');
        $type->when(['decimal', 'float', 'double'])->with(
            $form->number('NUMERIC_SCALE', '小数点')->help('数值类型才有小数点')
        );
        $form->text('COLUMN_DEFAULT', '默认值');
        $form->checkbox('ATTR', '属性')->options(['index' => '索引', 'unsigned' => '非负'])->help('索引加快查询速度，非负表示该字段的数值不允许为负数');
    }

    public function add()
    {
        if (request()->isGet()) {

            $builder = $this->builder($this->pageTitle, $this->addText ?: __blang('bilder_page_add_text'), 'add');
            $form = $builder->form();
            $data = [];
            $this->form = $form;
            $this->isEdit = 0;
            $this->buildForm($this->isEdit, $data);
            $form->fill($data);
            $form->method('post');

            return $builder->render();
        }

        $this->checkToken();

        return $this->save();
    }

    /**
     * @param string $field
     * @return array
     */
    protected function getFieldInfo($name)
    {
        $field = $this->dbLogic->getFieldInfo($this->tableName, $name);
        if (!$field) {
            return [];
        }
        $ATTR = [];
        if (strpos($field['COLUMN_TYPE'], 'unsigned')) {
            $ATTR['unsigned'] = 'unsigned';
        }
        $keys = $this->dbLogic->getKeys($this->tableName, $name);
        foreach ($keys as $key) {
            if (strtoupper($key['INDEX_NAME']) == 'PRIMARY') {
                $ATTR['index'] = 'index';
                continue;
            }

            if ($key['NON_UNIQUE'] == 1) {
                $ATTR['index'] = 'index';
            } else {
                $ATTR['unique'] = 'unique';
            }
        }

        $field['ATTR'] = $ATTR;
        $field['IS_NULLABLE'] = $field['IS_NULLABLE'] == 'YES';

        return $field;
    }

    public function edit()
    {
        $id = input('id');

        if (request()->isGet()) {

            $builder = $this->builder($this->pageTitle, $this->editText ?: __blang('bilder_page_edit_text'), 'edit');

            $field = $this->getFieldInfo($id);

            if (!$field) {
                return $builder->layer()->close(0, __blang('bilder_data_not_found'));
            }

            $form = $builder->form();
            $this->form = $form;
            $this->isEdit = 1;
            $this->buildForm($this->isEdit, $field);
            $form->fill($field);
            $form->method('put');

            return $builder->render();
        }

        $this->checkToken();

        return $this->save($id);
    }

    /**
     * 保存数据
     *
     * @param string $field
     * @return mixed
     */
    protected function save($id = '')
    {
        $data = request()->post();

        if (!isset($data['IS_NULLABLE'])) {
            $data['IS_NULLABLE'] = '0';
        }

        $data['ATTR'] = isset($data['ATTR']) ? $data['ATTR'] : [];

        $res = 0;
        if ($id) {
            $res = $this->dbLogic->changeField($this->tableName, $id, $data);
        } else {
            $this->dbLogic->addField($this->tableName, $data);
        }

        if (!$res) {
            $this->error(__blang('bilder_save_failed'));
        }

        return $this->builder()->layer()->closeRefresh(1, __blang('bilder_save_succeeded'));
    }

    public function delete()
    {
        $this->checkToken();

        $ids = input('post.ids', '');
        $ids = array_filter(explode(',', $ids), 'strlen');
        if (empty($ids)) {
            $this->error(__blang('bilder_parameter_error'));
        }
        $res = 0;
        foreach ($ids as $id) {
            $field = $this->getFieldInfo($id);
            $field['COLUMN_NAME'] .= '_del_at_' . time();
            $field['IS_NULLABLE'] = 1;
            if ($this->dbLogic->changeField($this->tableName, $id, $field)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success(__blang('bilder_delete_{:num}_records_succeeded', ['num' => $res]));
        } else {
            $this->error(__blang('bilder_delete_failed'));
        }
    }

    /**
     * @return array
     */
    protected function getProtectedFields()
    {
        $sqlFile = Module::getInstance()->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql';
        $content = file_get_contents($sqlFile);

        preg_match('/CREATE TABLE IF NOT EXISTS `__PREFIX__cms_content`\s*\((.+?)\)\s*ENGINE=InnoDB/is', $content, $mch);
        $cms_content_create = $mch[1];
        preg_match_all('/`(\w+)`.+?COMMENT/is', $cms_content_create, $matches);

        $fields = [];
        foreach ($matches[1] as $i => $match) {
            $fields[] = $match;
        }

        return $fields;
    }
}
