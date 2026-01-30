<?php
namespace Core;

use App\Config\Db;
use App\Util\Type;

/* 模型 */
class Model extends Base {

  public $conn = null;          // 连接
  private $name = 'Model';      // 名称
  private $table = '';          // 数据表
  private $columns = '*';       // 字段
  private $where = '';          // 条件
  private $group = '';          // 分组
  private $having = '';         // 筛选
  private $order = '';          // 排序
  private $limit = '';          // 限制
  private $args = [];           // 参数
  private $keys = '';           // 新增-名
  private $values = '';         // 新增-值
  private $data = '';           // 更新-数据
  private $sql = '';            // SQL
  private $id = 0;              // 自增ID
  private $nums = 0;            // 影响行数

  /* 获取连接 */
  protected function DBConn(string $name='default'): object {
    // 配置
    $cfg = Db::Config($name);
    // 连接
    if(!$this->conn) {
      try {
        $this->conn = new \PDO(
          $cfg['driver'].':host='.$cfg['host'].';dbname='.$cfg['database'].';port='.$cfg['port'],
          $cfg['user'],
          $cfg['password'],
          [
            // 长链接
            \PDO::ATTR_PERSISTENT => $cfg['persistent'],
            // 异常设置
            \PDO::ATTR_ERRMODE => 2
          ]
        );
        // 设置编码
        if($this->conn) $this->conn->exec('SET NAMES "'.$cfg['charset'].'";');
      } catch (\Exception $e) {
        self::Print('[ '.$this->name.' ] Conn:', $e->getMessage());
      }
    }
    // 返回
    return $this->conn;
  }

  /* 执行 */
  function Exec($conn, string $sql, array $args=[]): ?object {
    if(!$conn) return null;
    if(empty($sql)) {
      self::Error('[ '.$this->name.' ]', 'Exec: SQL不能为空!');
      return null;
    }
    try {
      $stmt = $conn->prepare($sql);
      foreach($args as $k=>$v){
        $stmt->bindValue($k+1, $v);
      }
      $this->nums = $stmt->execute();
      $this->id = $conn->lastInsertId();
      return $stmt;
    }catch (\Exception $e){
      self::Error('[ '.$this->name.' ]', 'Exec: '.$e->getMessage());
      return null;
    }
  }

  /* 获取-SQL */
  function GetSql() : array {
    return [$this->sql, $this->args];
  }
  /* 获取-自增ID */
  function GetID() : int {
    return $this->id;
  }
  /* 获取-影响行数 */
  function GetNums() : int {
    return $this->nums;
  }

  /* 表 */
  function Table(string $table): void {
    $this->table = $table;
  }
  /* 分区 */
  function Partition(...$partition): void {
    $this->table .= ' PARTITION('.implode(',', $partition).')';
  }
  /* 关联-INNER */
  function Join(string $table, string $on): void {
    $this->table .= ' INNER JOIN ' . $table . ' ON ' . $on;
  }
  /* 关联-LEFT */
  function LeftJoin(string $table, string $on): void {
    $this->table .= ' LEFT JOIN ' . $table . ' ON ' . $on;
  }
  /* 关联-RIGHT */
  function RightJoin(string $table, string $on): void {
    $this->table .= ' RIGHT JOIN ' . $table . ' ON ' . $on;
  }
  /* 关联-FULL */
  function FullJoin(string $table, string $on): void {
    $this->table .= ' FULL JOIN ' . $table . ' ON ' . $on;
  }
  /* 字段 */
  function Columns(...$fields): void {
    $this->columns = implode(',', $fields);
  }
  /* 条件 */
  function Where(string $where, ...$args): void {
    $this->where = ' WHERE ' . $where;
    $this->args = $args;
  }
  /* 分组 */
  function Group(string ...$group): void {
    $this->group = ' GROUP BY ' . implode(',', $group);
  }
  /* 筛选 */
  function Having(string $having): void {
    $this->having = ' HAVING ' . $having;
  }
  /* 排序 */
  function Order(string ...$order): void {
    $this->order = ' ORDER BY ' . implode(',', $order);
  }
  /* 限制 */
  function Limit(int $start, int $limit): void {
    $this->limit = ' LIMIT ' . $start . ',' . $limit;
  }
  /* 分页 */
  function Page(int $page, int $limit): string {
    return $this->limit = ' LIMIT ' . (($page - 1) * $limit). ',' . $limit;
  }

  /* 查询-SQL */
  function SelectSQL(): array {
    // 验证
    if($this->table==='') {
      self::Print('[ '.$this->name.' ]', 'Select: 表不能为空!');
      return ['', $this->args];
    }
    if($this->columns==='') {
      self::Print('[ '.$this->name.' ]', 'Select]: 字段不能为空!');
      return ['', $this->args];
    }
    // SQL
    $this->sql = 'SELECT '.$this->columns.' FROM '.$this->table;
    $this->table = '';
    $this->columns = '*';
    if($this->where !== '') {
      $this->sql .= $this->where;
      $this->where = '';
    }
    if($this->group !== '') {
      $this->sql .= $this->group;
      $this->group = '';
    }
    if($this->having !== '') {
      $this->sql .= $this->having;
      $this->having = '';
    }
    if($this->order !== '') {
      $this->sql .= $this->order;
      $this->order = '';
    }
    if($this->limit !== '') {
      $this->sql .= $this->limit;
      $this->limit = '';
    }
    // 参数
    $args = $this->args;
    $this->args = [];
    // 结果
    return [$this->sql, $args];
  }
  /* 查询-多条 */
  function Find(string $sql='', array $args=[]): array {
    if($sql=='') {
      list($sql, $args) = $this->SelectSQL();
    }
    $stmt = $this->Exec($this->conn, $sql, $args);
    return $stmt?$this->DataAll($stmt):[];
  }
  /* 查询-多条数据 */
  function DataAll(object $stmt): array {
    return $stmt?$stmt->fetchAll(\PDO::FETCH_ASSOC):[];
  }
  /* 查询-单条 */
  function FindFirst(string $sql='', array $args=[]): array {
    if($sql=='') {
      $this->Limit(0, 1);
      list($sql, $args) = $this->SelectSQL();
    }
    $stmt = $this->Exec($this->conn, $sql, $args);
    return $stmt?$this->Data($stmt):false;
  }
  /* 查询-单条数据 */
  function Data(object $stmt): array | bool {
    return $this->nums>0?$stmt->fetch(\PDO::FETCH_ASSOC):false;
  }

  /* 添加-单条 */
  function Values(array $data): void {
    $keys = $vals = $this->args = [];
    foreach($data as $k=>$v){
      $keys[] = $k;
		  $vals[] = '?';
      $this->args[] = $v;
    }
    // 字段
    $this->keys = implode(',', $keys);
    $this->values = '(' . implode(',', $vals). ')';
  }
  /* 添加-多条 */
  function ValuesAll(array $data): void {
    $keys = $vals = $tmp = $this->args = [];
    foreach($data[0] as $k=>$v){
      $keys[] = $k;
    }
    foreach ($data as $row) {
      $tmp = [];
      foreach ($row as $v) {
        $tmp[] = '?';
        $this->args[] = $v;        
      }
      $vals[] = '(' . implode(',', $tmp) . ')';
    }
    $this->keys = implode(',', $keys);
    $this->values = implode(',', $vals);
  }
  /* 添加-SQL */
  function InsertSQL(): array {
    if($this->table==''){
      self::Error('[ '.$this->name.' ]', 'Insert: 表不能为空!');
      return ['', $this->args];
    }
    if($this->keys=='' || $this->values==''){
      self::Error('[ '.$this->name.' ]', 'Insert: 数据不能为空!');
      return ['', $this->args];
    }
    $this->sql = 'INSERT INTO `' . $this->table . '`(' . $this->keys . ') VALUES ' . $this->values;
    // 重置
    $this->table = '';
    $this->keys = '';
    $this->values = '';
    // 参数
    $args = $this->args;
    $this->args = [];
    // 结果
    return [$this->sql, $args];
  }
  /* 添加-执行 */
  function Insert(string $sql='', array $args=[]): bool {
    if($sql=='') {
      list($sql, $args) = $this->InsertSQL();
    }
    $stmt = $this->Exec($this->conn, $sql, $args);
    return $stmt?true:false;
  }
  /* 添加-自增ID */
  function LastInsertId($conn): int {
    return $conn->lastInsertId();
  }

  /* 更新-数据 */
  function Set(array $data = []): void {
    $vals = '';
    $this->args = [];
    foreach($data as $k=>$v){
      $vals .= $k . '=?,';
      $this->args[] = $v;
    }
    $this->data = !empty($vals)?rtrim($vals, ','):'';
  }
  /* 更新-SQL */
  function UpdateSQL(): array {
    if($this->table == '') {
      self::Error('[ '.$this->name.' ]', 'Update: 表不能为空!');
      return ['', $this->args];
    }
    if($this->data == '') {
      self::Error('[ '.$this->name.' ]', 'Update: 数据不能为空!');
      return ['', $this->args];
    }
    if($this->where == '') {
      self::Error('[ '.$this->name.' ]', 'Update: 条件不能为空!');
      return ['', $this->args];
    }
    // SQL
    $this->sql = 'UPDATE ' . $this->table . ' SET ' . $this->data . ' WHERE ' . $this->where;
    // 重置
    $this->table = '';
    $this->data = '';
    $this->where = '';
    // 参数
    $args = $this->args;
    $this->args = [];
    return [$this->sql, $args];
  }
  /* 更新-执行 */
  function Update(): bool {
    list($sql, $args) = $this->UpdateSQL();
    $stmt = $this->Exec($this->conn, $sql, $args);
    return $stmt?true:false;
  }

  /* 删除-SQL */
  function DeleteSQL(): array {
    if($this->table == ''){
      self::Error('[ '.$this->name.' ]', 'Delete: 表不能为空!');
      return ['', $this->args];
    }
    if($this->where == ''){
      self::Error('[ '.$this->name.' ]', 'Delete: 条件不能为空!');
      return ['', $this->args];
    }
    $this->sql = 'DELETE FROM `' . $this->table . '` WHERE ' . $this->where;
    // 重置
    $this->table = '';
    $this->where = '';
    // 参数
    $args = $this->args;
    $this->args = [];
    return [$this->sql, $args];
  }

  /* 删除-执行 */
  function Delete(): bool {
    list($sql, $args) = $this->DeleteSQL();
    $stmt = $this->Exec($this->conn, $sql, $args);
    return $stmt?true:false;
  }

}