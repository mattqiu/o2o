<?php



class LifeServiceAction extends CommonAction {

    public function _initialize() {
        parent::_initialize();
    }

    /**
     * 审查所有审查且已通过的
     */
    public function index() {
        $LifeModel = D('LifeService');
        import('ORG.Util.Page'); // 导入分页类
        $map = array('life.closed' => 0, 'life.level' => 1, 'life.audit' => 1);

        // 保存搜索信息到cookie中
        if(cookie('LifeServiceSearchIndexMessage')){
            $map = cookie('LifeServiceSearchIndexMessage');
            if(cookie('LifeServiceSearchIndexMessageName')){
                $keyword = cookie('LifeServiceSearchIndexMessageName');
                $this->assign('keyword', $keyword);
                $map['life.store_name'] = array('LIKE', '%' . $keyword . '%');
            }
            if(isset($map['life.level']) && $map['life.level']){
                if(!in_array($map['life.level'], [1,2])){
                    $map['life.level'] = 2;
                }
                $this->assign('level', $map['life.level']);
            }
            if(isset($map['life.city_id']) && $map['life.city_id']){
                $this->assign('city_id', $map['life.city_id']);
            }
            if(isset($map['life.area_id']) && $map['life.area_id']){
                $this->assign('area_id', $map['life.area_id']);
            }
            if(isset($map['life.business_id']) && $map['life.business_id']){
                $this->assign('business_id', $map['life.business_id']);
            }
        }

        // 根据提交方式判断，是否清除cookie相应信息
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            if ($keyword = trim($this->_param('keyword', 'htmlspecialchars'))){
                $map['life.store_name'] = array('LIKE', '%' . $keyword . '%');
                // 在获取以后再赋值s
                $this->assign('keyword', $keyword);
            }
            if(!$keyword){
                unset($map['life.store_name']);
                $this->assign('keyword', '');
            }

            // 店铺类型，1 个人    2 企业
            if ($level = (int) $this->_param('level')) {
                if(!in_array($level, [1,2])){
                    $level = 2;
                }
                $map['life.level'] = $level;
                $this->assign('level', $level);
            }

            if ($city_id = (int) $this->_param('city_id')) {
                $map['life.city_id'] = $city_id;
                $this->assign('city_id', $city_id);
            }
            if($city_id == 0){
                unset($map['life.city_id']);
                unset($map['life.area_id']);
                unset($map['life.business_id']);
                $this->assign('city_id', 0);
                $this->assign('area_id', 0);
                $this->assign('business_id', 0);
            }

            if ($area_id = (int) $this->_param('area_id')) {
                $map['life.area_id'] = $area_id;
                $this->assign('area_id', $area_id);
            }
            if($area_id == 0){
                unset($map['life.area_id']);
                unset($map['life.business_id']);
                $this->assign('area_id', 0);
                $this->assign('business_id', 0);
            }

            if ($business_id = (int) $this->_param('business_id')) {
                $map['life.business_id'] = $business_id;
                $this->assign('business_id', $business_id);
            }
            if($business_id == 0){
                unset($map['life.business_id']);
                $this->assign('business_id', 0);
            }
        }

        // 保存搜索信息到cookie中，有效时间15分钟
        cookie('LifeServiceSearchIndexMessage', $map, 900);
        cookie('LifeServiceSearchIndexMessageName', $map['life.store_name'], 900);
//        var_dump($map);

        $count = $LifeModel->where($map)->count();   // 查询满足要求的总记录数
        $Page = new Page($count, 25);   // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show();   // 分页显示输出
        $list = $LifeModel
            ->alias('life')
            ->field('life.*, city.name as city_name, area.area_name')
            ->join('LEFT JOIN bao_city city ON city.city_id = life.city_id')
            ->join('LEFT JOIN bao_area area ON area.area_id = life.area_id')
            ->where($map)
            ->order(array('life.update_time' => 'desc'))
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        foreach ($list as &$li){
            if(!$li['cate_id']){
                $li['cate_name'] = '暂无分类';
            }
        }

        //
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出

        $this->display(); // 输出模板
    }

    /**
     * @param int $store_id
     * @param int $level
     * 查看商家详情
     */
    public function detail($store_id = 0, $level = 0) {
        if ($store_id = (int) $store_id) {
            $obj = D('LifeService');
            if (!$detail = $obj->find($store_id)) {
                $this->baoError('请选择要查看的超市商家');
            }
            if($level == 1){
                // 个人用户申请的店铺
                // 查询个人店铺的身份证照片
                $pics = $obj
                    ->join('LEFT JOIN bao_shop ON bao_ele.shop_id=bao_shop.shop_id')
                    ->join('LEFT JOIN bao_presonal_store_open_auth ON bao_presonal_store_open_auth.uid=bao_shop.user_id')
                    ->field('bao_presonal_store_open_auth.id_face, bao_presonal_store_open_auth.id_back')
                    ->where(array('store_id' => $store_id))
                    ->find();
                $detail['id_face'] = $pics['id_face'];
                $detail['id_back'] = $pics['id_back'];

//                var_dump($pics);
                $this->assign('detail', $detail);
                $this->display();

            }elseif ($level == 2){
                // 企业用户申请的店铺
                // 查询企业店铺营业执照
                $pics = M('Com_store_open_auth')
                    ->field('business_license, other_pic')
                    ->where(array('store_id' => $store_id, 'store_class_id' => 2))
                    ->find();
                $detail['business_license'] = $pics['business_license'];
                if(!$pics['other_pic']){
                    $detail['other_pic'] = 0;
                }
                $pics = array_filter(explode(',', $pics['other_pic']));
//                var_dump($pics);

                $this->assign('detail', $detail);
                $this->assign('pics', $pics);
                $this->display();
            }else{
                $this->baoError('请选择要查看的商家');
            }
        } else {
            $this->baoError('请选择要查看的商家');
        }
    }

    public function create() {
        if ($this->isPost()) {
            $data = $this->createCheck();
            $obj = D('Ele');
            $cate = $this->_post('cate', false);
            $cate = implode(',', $cate);
            $data['cate'] = $cate;
            if ($obj->add($data)) {
                $this->baoSuccess('添加成功', U('ele/index'));
            }
            $this->baoError('操作失败！');
        } else {
            $this->display();
        }
    }

    private function createCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->create_fields);
        $data['store_id'] = (int) $data['store_id'];
        if (empty($data['store_id'])) {
            $this->baoError('ID不能为空');
        }
        if (!$shop = D('Shop')->find($this->shop)) {
            $this->baoError('商家不存在');
        }
        $data['shop_name'] = $shop['shop_name'];
        $data['lng'] = $shop['lng'];
        $data['lat'] = $shop['lat'];
        $data['city_id'] = $shop['city_id'];
        $data['area_id'] = $shop['area_id'];
        $data['business_id'] = $shop['business_id'];

        $data['is_open'] = (int) $data['is_open'];
        $data['is_pay'] = (int) $data['is_pay'];
        $data['is_fan'] = (int) $data['is_fan'];
        $data['is_new'] = (int) $data['is_new'];
        $data['sold_num'] = (int) $data['sold_num'];
        $data['month_num'] = (int) $data['month_num'];
        $data['rate'] = (int) $data['rate'];
        $data['audit'] = (int) $data['audit'];
        $data['distribution'] = (int) $data['distribution'];
        $data['intro'] = htmlspecialchars($data['intro']);
        if (empty($data['intro'])) {
            $this->baoError('说明不能为空');
        }
        $data['orderby'] = (int) $data['orderby'];
        return $data;
    }

    public function edit($store_id = 0) {
        if ($store_id = (int) $store_id) {
            $obj = D('Ele');
            if (!$detail = $obj->find($store_id)) {
                $this->baoError('请选择要编辑的外卖商家');
            }
            if ($this->isPost()) {
                $data = $this->editCheck();
                $data['store_id'] = $store_id;
                $cate = $this->_post('cate', false);
                $cate = implode(',', $cate);
                $data['cate'] = $cate;

                if (false !== $obj->save($data)) {
                    $this->baoSuccess('操作成功', U('ele/index'));
                }
                echo $obj->getLastSql();die;
                $this->baoError($obj->getError());
            } else {
                $cate = explode(',', $detail['cate']);
                $this->assign('cate', $cate);
                $this->assign('detail', $detail);
                $this->display();
            }
        } else {
            $this->baoError('请选择要编辑的外卖商家');
        }
    }

    private function editCheck() {
        $data = $this->checkFields($this->_post('data', false), $this->edit_fields);

        $data['is_open'] = (int) $data['is_open'];
        $data['is_pay'] = (int) $data['is_pay'];
        $data['is_fan'] = (int) $data['is_fan'];
        $data['is_new'] = (int) $data['is_new'];
        $data['sold_num'] = (int) $data['sold_num'];
        $data['month_num'] = (int) $data['month_num'];
        $data['distribution'] = (int) $data['distribution'];
        $data['audit'] = (int) $data['audit'];
        $data['intro'] = htmlspecialchars($data['intro']);
        $data['rate'] = (int) $data['rate'];
        if (empty($data['intro'])) {
            $this->baoError('说明不能为空');
        }
        $data['orderby'] = (int) $data['orderby'];
        return $data;
    }

    public function delete($store_id = 0) {
        // 调用私有方法
        self::setDelete($store_id, 'LifeService/index', 'LifeService', 'store_id');
    }

    public function shiftdelete($store_id = 0) {
        // 调用私有方法
        self::setDelete($store_id, 'ele/apply', 'Ele', 'store_id');
    }

    /**peace
     * 外卖商家审核
     */
    public function apply() {
        $ele = D('Ele');
        import('ORG.Util.Page'); // 导入分页类
        $map = array('bao_ele.closed' => 0, 'bao_ele.audit' => 0, 'bao_ele.level' => 1);

        // 保存搜索信息到cookie中
        if(cookie(md5('EleSearchApplyMessage'))){
            $map = cookie(md5('EleSearchApplyMessage'));
            if(cookie(md5('EleSearchApplyMessageName'))){
                $map['bao_ele.store_name'] = cookie(md5('EleSearchApplyMessageName'));
            }
        }

        // 根据提交方式判断，是否清除cookie相应信息
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            if ($keyword = $this->_param('keyword', 'htmlspecialchars')){
            $map['bao_ele.store_name'] = array('LIKE', '%' . $keyword . '%');
                // 在获取以后再赋值
                $this->assign('keyword', $keyword);
            }
            if(!$keyword){
                unset($map['bao_ele.store_name']);
            }

            // 店铺类型，1 个人    2 企业
            if ($level = (int) $this->_param('level')) {
                $map['bao_ele.level'] = $level;
                $this->assign('level', $level);
            }

            // 店铺类型，0未审查    2 未通过审查
            if ($audit = (int) $this->_param('audit')) {
                if($audit == 1){
                    $audit = 0;
                }
                $map['bao_ele.audit'] = $audit;
                $this->assign('audit', $audit);
            }

            if ($city_id = (int) $this->_param('city_id')) {
                $map['bao_ele.city_id'] = $city_id;
                $this->assign('city_id', $city_id);
            }
            if($city_id === 0){
                unset($map['bao_ele.city_id']);
                unset($map['bao_ele.area_id']);
                unset($map['bao_ele.business_id']);
            }

            if ($area_id = (int) $this->_param('area_id')) {
                $map['bao_ele.area_id'] = $area_id;
                $this->assign('area_id', $area_id);
            }
            if($area_id === 0){
                unset($map['bao_ele.area_id']);
                unset($map['bao_ele.business_id']);
            }

            if ($business_id = (int) $this->_param('business_id')) {
                $map['bao_ele.business_id'] = $business_id;
                $this->assign('business_id', $business_id);
            }
            if($business_id === 0){
                unset($map['bao_ele.business_id']);
            }
        }

        if($_SERVER['REQUEST_METHOD'] == 'GET') {
            $keyword = substr(str_replace(' ', '', $map['bao_ele.store_name'][1]), 1, -1);
            if($keyword != ''){
                $this->assign('keyword', $keyword);
            }
        }

        // 保存搜索信息到cookie中，有效时间15分钟
        cookie(md5('EleSearchApplyMessage'), $map, 900);
        cookie(md5('EleSearchApplyMessageName'), $map['bao_ele.store_name'], 900);
//        var_dump($map);

        $count = $ele->where($map)->count();   // 查询满足要求的总记录数
        $Page = new Page($count, 25);   // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $Page->show();   // 分页显示输出
        // 根据更新时间升序
        $list = $ele
            ->field('bao_ele.*, bao_users.nickname, bao_users.mobile, bao_area.area_name, bao_city.name as city_name')
            ->join('LEFT JOIN bao_shop ON bao_shop.shop_id = bao_ele.shop_id')
            ->join('LEFT JOIN bao_users ON bao_users.user_id = bao_shop.user_id')
            ->join('LEFT JOIN bao_city ON bao_city.city_id = bao_ele.city_id')
            ->join('LEFT JOIN bao_area ON bao_area.area_id = bao_ele.area_id')
            ->order(array('bao_ele.audit' => 'ASC', 'bao_ele.update_time' => 'DESC'))
            ->where($map)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        // 是否选中搜索栏名
        $this->assign('audit_2', $map['bao_ele.audit']);
        $this->assign('city_id_2', $map['bao_ele.city_id']);
        $this->assign('area_id_2', $map['bao_ele.area_id']);
        $this->assign('business_id_2', $map['bao_ele.business_id']);
        $this->assign('level_2', $map['bao_ele.level']);
        $this->assign('list', $list); // 赋值数据集
        $this->assign('page', $show); // 赋值分页输出
        // 传递市区县数据
        $this->assign('cities', M('City')->select());
        if($map['bao_ele.city_id']){
            $this->assign('areas',
                M('Area')->where(array('city_id' => $map['bao_ele.city_id']))->select()
            );
        }
        if($map['bao_ele.area_id']){
            $this->assign('businesses',
                M('Business')->where(array('area_id' => $map['bao_ele.area_id']))->select()
            );
        }
        $this->display(); // 输出模板
    }

    public function exame($store_id = 0) {
        if ($store_id = (int) $store_id) {
            $obj = D('Ele');
            if (!$this->ele_info = $obj->find($store_id)) {
               // echo 111;
               // var_dump(D('Ele')->getLastSql());
                $this->baoError('请选择要审核的商家');
            }
            $this->shop_info = D('shop')->where(array('shop_id'=>$this->ele_info['shop_id']))->find();
            $this->member_info = D('Users')->find($this->shop_info['user_id']);
            if($this->ele_info['level']==1){
                $this->presonl_exame();
                }else{
                    $this->com_exame();
            }
        } else {
            $this->baoError('请选择要编辑的美食商家');
        }
    }

    public function presonl_exame(){
        $auth_info = D('presonal_store_open_auth')->find($this->shop_info['user_id']);
        if(IS_POST){
            if(D('Ele')->save($_POST['data'])){
                 if($_POST['data']['audit']=='2'){
                        D('Sms')->sendSms('ele_open_err', $this->member_info['mobile'],array('username'=>$this->member_info['nickname']));
                    }elseif($_POST['data']['audit']=='1'){
                        //更新认证状态
                        D('presonal_store_open_auth')->where(array('uid'=>$this->shop_info['user_id']))->save(array('auth'=>1));
                        //更新商家状态
                        //D('shop')->where(array('shop_id'=>$this->shop_info['shop_id']))->save(array('audit'=>1));
                        //检测我佣有的店铺
                        $my_store = D('MyHaveStore')->where(array('uid'=>$this->shop_info['user_id'],'sc_id'=>2))->count();
                        if(!$my_store){
                            D('MyHaveStore')->add(array('uid'=>$this->shop_info['user_id'],'sc_id'=>2));
                        }
                        
                        D('Sms')->sendSms('ele_open_ok', $this->member_info['mobile'],array('username'=>$this->member_info['nickname'],'shopname'=>$this->ele_info['shop_name']));
                    }
                $this->baoSuccess('审核成功', U('ele/apply'));
            }
        }
        
        //var_dump($auth_info);
        $this->detail = $auth_info;
        $this->display('presonl_exame');
    }

    public function com_exame(){
        $auth_info = D('ComStoreOpenAuth')->where(array('store_id'=>$this->ele_info['store_id'],'store_class_id'=>2))->find();
       // var_dump($_POST);die;
        if(IS_POST){
            if(D('Ele')->save($_POST['data'])){
                 if($_POST['data']['audit']=='2'){
                        D('Sms')->sendSms('ele_open_err', $this->member_info['mobile'],array('nickname'=>$this->member_info['nickname'],'shopname'=>$this->ele_info['shop_name'],'explain'=>$_POST['data']['exame_explain']));
                    }elseif($_POST['data']['audit']=='1'){
                        //更新认证状态
                        D('ComStoreOpenAuth')->where(array('store_id'=>$this->chaoshi_info['store_id'],'store_class_id'=>1))->save(array('audit'=>1));
                        //更新商家状态
                        //D('shop')->where(array('shop_id'=>$this->shop_info['shop_id']))->save(array('audit'=>1));
                        //检测我佣有的店铺
                        $my_store = D('MyHaveStore')->where(array('uid'=>$this->shop_info['user_id'],'sc_id'=>1))->count();
                        if(!$my_store){
                            D('MyHaveStore')->add(array('uid'=>$this->shop_info['user_id'],'sc_id'=>1));
                        }
                       D('Sms')->sendSms('ele_open_ok', $this->member_info['mobile'],array('username'=>$this->member_info['nickname'],'shopname'=>$this->ele_info['shop_name']));
                    }
                $this->baoSuccess('审核成功', U('ele/apply'));
            }else{
               $this->baoError('无操作'); 
            }
        }

        $auth_info['other_pic']=explode(",",$auth_info['other_pic']);
        //var_dump($auth_info);
        $this->detail = $auth_info;
        $this->display('com_exame');
    }

    public function opened($store_id = 0, $type = 'open') {
        if (is_numeric($store_id) && ($store_id = (int) $store_id)) {
            $obj = D('Ele');
            $is_open = 0;
            if ($type == 'open') {
                $is_open = 1;
            }
            $obj->save(array('store_id' => $store_id, 'is_open' => $is_open));

            $this->baoSuccess('操作成功！', U('ele/index'));
        }
    }

    /**peace
     * 设置为店铺整顿
     */
    public function reorganize(){
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            // 调用公共方法  I('store_id', '0', 'intval'), I('type'), 'Ele'
            $this->reorganizeStore(I('post.store_id'), 1, 'Ele');
        }else{
            $this->reorganizeStore(I('get.store_id'), 1, 'Ele');
        }
    }

    /**peace
     * 取消店铺整顿，变为正常
     */
    public function cancelReorganize(){
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            // 调用公共方法  I('store_id', '0', 'intval'), I('type'), 'Ele'
            $this->reorganizeStore(I('post.store_id'), 0, 'Ele');
        }else{
            $this->reorganizeStore(I('get.store_id'), 0, 'Ele');
        }
    }

    /**peace
     * 初始化搜索引擎，商家列表
     */
    public function initialIndex(){
        cookie('LifeServiceSearchIndexMessage', NULL, 0);
        $this->baoSuccess('初始化成功', U('lifeService/index'), 1000);
    }

    /**peace
     * 初始化搜索引擎，审核列表
     */
    public function initialApply(){
        cookie('EleSearchApplyMessage', NULL, 0);
        $this->baoSuccess('初始化成功', U('ele/apply'), 1000);
    }

}
