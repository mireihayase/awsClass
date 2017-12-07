<?php

namespace App\Src\TextSearch;

/**
 * AWS CloudSearch 転置インデックス操作時 ドキュメント準備クラス
 */
class CloudSearchDocument implements CloudSearchDocumentInterface
{
    /** @var  string ユーザーエンドポイント識別用接頭辞(mem|vn|sr)*/
    private $endpoint_prefix;

    /** @var array インデックス作成用メンバーデータ一時置き場 */
    private $temp_values = [];

    /** @var string 転置インデックス特定用ID(RDBのPKに相当) */
    private $id;

    /** @var array 値を格納するための配列 */
    private $fields;

    /** @var string 操作を指定:: 'add' or 'delete' */
    private $type;

    /**
     * 補完用マジックメソッド
     *
     * @param $name
     * @param $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setField($name, $value);
    }

    /**
     * 補完用マジックメソッド
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if ( ! isset($this->fields[$name])) {
            return null;
        }
        return $this->fields[$name];
    }

    /**
     * 補完用マジックメソッド
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->fields);
    }

    /**
     * セット済みフィールドを返す
     *
     * @return array
     */
    public function toArray()
    {
        return $this->fields;
    }

    /**
     * フィールドをセット
     *
     * @param      $key
     * @param      $value
     * @param bool $filterNullFields
     * @return bool
     */
    public function setField($key, $value, $filterNullFields = true)
    {
        if ($filterNullFields && is_null($value)) {
            return false;
        }
        $this->fields[$key] = $value;
        return true;
    }

    /**
     * @param       $key
     * @param array $array
     * @return mixed
     */
    private function getValueFromArray($key, array $array)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        return null;
    }

    /**
     * @param $path
     * @return mixed|null
     */
    public function getField($path)
    {
        $currentField = null;
        foreach (explode('.', $path) as $key) {
            $currentField = $this->getValueFromArray($key, $currentField);
            if (is_null($currentField)) {
                return null;
            }
        }
        return $currentField;
    }

    /**
     * ドキュメントフィールドに値をセット
     *
     * @param array $fields
     * @param bool  $filterNullFields
     */
    public function setFields(array $fields, $filterNullFields = true)
    {
        if ($filterNullFields) {
            $fields = array_filter($fields, [$this, 'filterNullField']);
        }
        $this->fields = $fields;
    }

    /**
     * @param $value
     * @return bool
     */
    private function filterNullField($value)
    {
        if ( ! is_null($value) && ! is_array($value)) {
            $value = trim($value);
        }
        return ! is_null($value) && $value !== '';
    }

    /**
     * @param array $hit
     */
    public function fillWithHit(array $hit)
    {
        $this->id = $hit['id'];
        foreach ($hit['fields'] as $key => $field) {
            if (is_array($field) && count($field) === 1) {
                $this->fields[$key] = $field[0];
            } else {
                $this->fields[$key] = $field;
            }
        }
    }

    /**
     * ドキュメントタイプをセット::add
     *
     * @return void
     */
    public function setTypeAdd()
    {
        $this->type = 'add';
    }

    /**
     * ドキュメントタイプをセット::delete
     *
     * @return void
     */
    public function setTypeDelete()
    {
        $this->type = 'delete';
    }

    /**
     * マスタデータをテンプ領域にセット
     *
     * @param string $master_data 転置インデックスのもとになるマスタデータ
     * @return void
     */
    public function setMasterToWorkArea($master_data)
    {
        $this->temp_values = $master_data;
    }

    protected function getMasterFromWorkArea()
    {
        return $this->temp_values;
    }

    /**
     * ドキュメントに各種パラメーターをセット
     *
     * @return array
     */
    public function getDocument()
    {
        $document = [
            'type'   => $this->type,
            'id'     => $this->id,
            'fields' => $this->filterBadCharacters($this->fields)
        ];
        return array_filter($document);
    }

    /**
     * 不正文字列を簡易サニタイズ
     *
     * @param array $fields
     * @return mixed
     */
    private function filterBadCharacters($fields)
    {
        if (is_null($fields)) {
            return null;
        }
        $badCharacters = ['\u0015'];
        return json_decode(str_replace($badCharacters, '', json_encode($fields)), true);
    }

    /**
     * 転置インデックス作成用ドキュメントに各種パラメーターをセット# member_data_values用
     *
     * @return array
     */
    public function getPostMemDocuments()
    {
        $documentArray = [];
        foreach ($this->getMasterFromWorkArea() as $value) {
            $document['type'] = 'add';
            // 一意Keyを作成: member_data_values.id + customer
            $document['id'] = $value['id'] . '_' . $value['customer'];
            $document['fields'] = [
                'id' => $value['id'],
                'member_id' => $value['member_id'],
                'sheet_id' => $value['sheet_id'],
                'member_data_definition_id' => $value['member_data_definition_id'],
                'data' => $value['data'],
                'record_order' => $value['record_order'],
                'file_data_id' => $value['file_data_id'],
                'created_at' => $value['created_at'],
                'updated_at' => $value['updated_at'],
                'customer' => $value['customer']
            ];
            // 個々のドキュメントをArrayに束ねる
            $documentArray[] = $document;
        }
        return $documentArray;
    }

    /**
     * 転置インデックス作成用ドキュメントに各種パラメーターをセット# vn_answer用
     *
     * @return array
     */
    public function getPostVnDocuments()
    {
        $documentArray = [];
        foreach ($this->getMasterFromWorkArea() as $value) {
            $document['type'] = 'add';
            // 一意Keyを作成: vn_answers.id + customer
            $document['id'] = $value['id'] . '_' . $value['customer'];
            $document['fields'] = [
                'id' => $value['id'],
                'vn_event_id' => $value['vn_event_id'],
                'member_id' => $value['member_id'],
                'vn_form_definition_id' => $value['vn_form_definition_id'],
                'answer' => $value['answer'],
                'file_data_id' => $value['file_data_id'],
                'created_at' => $value['created_at'],
                'updated_at' => $value['updated_at'],
                'customer' => $value['customer']
            ];
            // 個々のドキュメントをArrayに束ねる
            $documentArray[] = $document;
        }
        return $documentArray;
    }

    /**
     * 転置インデックス作成用ドキュメントに各種パラメーターをセット# sr_answer用
     *
     * @return array
     */
#    public function getPostSrDocuments()
#    {
#        // sr_answersのスキーマが決まりしだい実装に着手
#    }

    /**
     * 転置インデックス削除用ドキュメントに各種パラメーターをセット
     *
     * @return array
     */
    public function getDeleteDocuments()
    {
        $documentArray = [];
        foreach ($this->getMasterFromWorkArea() as $value) {
            $document['type'] = 'delete';
            // 一意Keyを作成: member_data_values.id + customer
            $document['id'] = $value['id'] . '_' . $value['customer'];
            // 個々のドキュメントをArrayに束ねる
            $documentArray[] = $document;
        }
        return $documentArray;
    }

    /**
     * エンドポントの識別接頭辞を付与
     *
     * @param $obj
     * @return void
     */
    public function setEndpointPrefix($obj)
    {
        $this->endpoint_prefix = $obj;
    }
}