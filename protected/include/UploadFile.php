<?php
//�ļ���ͼƬ�ϴ���
/*
ʹ�÷�����
function _upload($upload_dir){
	$upload = new UploadFile();
	//�����ϴ��ļ���С
	$upload->maxSize=1024*1024*2;//���2M
	//�����ϴ��ļ�����
	$upload->allowExts  = explode(',','jpg,gif,png,bmp');

	//���ø����ϴ�Ŀ¼
	$upload->savePath ='../images/'.$upload_dir."/";
	$upload->saveRule = cp_uniqid;

	if(!$upload->upload())
	 {
		//�����ϴ��쳣
		$this->error($upload->getErrorMsg());
	}
	else 
	{
		//ȡ�óɹ��ϴ����ļ���Ϣ
		return $upload->getUploadFileInfo();
	}
}
*/
class UploadFile{

    // �ϴ��ļ������ֵ
    public $maxSize = -1;

    // �Ƿ�֧�ֶ��ļ��ϴ�
    public $supportMulti = true;

    // �����ϴ����ļ���׺
    //  ���ղ�����׺���
    public $allowExts = array();

    // �����ϴ����ļ�����
    // ���ղ������
    public $allowTypes = array();

    // ʹ�ö��ϴ�ͼƬ��������ͼ����
    public $thumb   =  false;
    // ����ͼ�����
    public $thumbMaxWidth;
    // ����ͼ���߶�
    public $thumbMaxHeight;
    // ����ͼǰ׺
    public $thumbPrefix   =  'thumb_';
    public $thumbSuffix  =  '';
    // ����ͼ����·��
    public $thumbPath = '';
    // ����ͼ�ļ���
    public $thumbFile		=	'';
    // �Ƿ��Ƴ�ԭͼ
    public $thumbRemoveOrigin = false;
    // ѹ��ͼƬ�ļ��ϴ�
    public $zipImages = false;
    // ������Ŀ¼�����ļ�
    public $autoSub   =  false;
    // ��Ŀ¼������ʽ ����ʹ��hash date
    public $subType   = 'hash';
    public $dateFormat = 'Ymd';
    public $hashLevel =  1; // hash��Ŀ¼���
    // �ϴ��ļ�����·��
    public $savePath = '';
    public $autoCheck = true; // �Ƿ��Զ���鸽��
    // ����ͬ���Ƿ񸲸�
    public $uploadReplace = false;

    // �ϴ��ļ���������
    // ��������� time uniqid com_create_guid ��
    // ������һ�������κβ����ĺ����� ����ʹ���Զ��庯��
    public $saveRule = '';

    // �ϴ��ļ�Hash��������
    // ��������� md5_file sha1_file ��
    public $hashType = 'md5_file';

    // ������Ϣ
    private $error = '';

    // �ϴ��ɹ����ļ���Ϣ
    private $uploadFileInfo ;

    /**
     +----------------------------------------------------------
     * �ܹ�����
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     */
    public function __construct($maxSize='',$allowExts='',$allowTypes='',$savePath='',$saveRule='')
    {
        if(!empty($maxSize) && is_numeric($maxSize)) {
            $this->maxSize = $maxSize;
        }
        if(!empty($allowExts)) {
            if(is_array($allowExts)) {
                $this->allowExts = array_map('strtolower',$allowExts);
            }else {
                $this->allowExts = explode(',',strtolower($allowExts));
            }
        }
        if(!empty($allowTypes)) {
            if(is_array($allowTypes)) {
                $this->allowTypes = array_map('strtolower',$allowTypes);
            }else {
                $this->allowTypes = explode(',',strtolower($allowTypes));
            }
        }
	   if(!empty($savePath)) {
            $this->savePath = $savePath;
        }	
        if(!empty($saveRule)) {
            $this->saveRule = $saveRule;
        }
	
        
    }

    private function save($file)
    {
        $filename = $file['savepath'].$file['savename'];
        if(!$this->uploadReplace && is_file($filename)) {
            // ������ͬ���ļ�
            $this->error	=	'�ļ��Ѿ����ڣ�'.$filename;
            return false;
        }
        // �����ͼ���ļ� ����ļ���ʽ
        if( in_array(strtolower($file['extension']),array('gif','jpg','jpeg','bmp','png','swf')) && false === getimagesize($file['tmp_name'])) {
            $this->error = '�Ƿ�ͼ���ļ�';
            return false;
        }
        if(!move_uploaded_file($file['tmp_name'], iconv('utf-8','gbk',$filename))) {
            $this->error = '�ļ��ϴ��������';
            return false;
        }
        if($this->thumb && in_array(strtolower($file['extension']),array('gif','jpg','jpeg','bmp','png'))) {
            $image =  getimagesize($filename);
            if(false !== $image) {
                //��ͼ���ļ���������ͼ
                $thumbWidth		=	explode(',',$this->thumbMaxWidth);
                $thumbHeight		=	explode(',',$this->thumbMaxHeight);
                $thumbPrefix		=	explode(',',$this->thumbPrefix);
                $thumbSuffix = explode(',',$this->thumbSuffix);
                $thumbFile			=	explode(',',$this->thumbFile);
                $thumbPath    =  $this->thumbPath?$this->thumbPath:$file['savepath'];
                // ����ͼ������ͼ
				if(file_exists(dirname(__FILE__).'/Image.class.php'))
				{
					require_once(dirname(__FILE__).'/Image.class.php');
					$realFilename  =  $this->autoSub?basename($file['savename']):$file['savename'];
					for($i=0,$len=count($thumbWidth); $i<$len; $i++) {
						$thumbname	=	$thumbPath.$thumbPrefix[$i].substr($realFilename,0,strrpos($realFilename, '.')).$thumbSuffix[$i].'.'.$file['extension'];
						Image::thumb($filename,$thumbname,'',$thumbWidth[$i],$thumbHeight[$i],true);
					}
					if($this->thumbRemoveOrigin) {
						// ��������ͼ֮��ɾ��ԭͼ
						unlink($filename);
					}
				}
            }
        }
        if($this->zipImags) {
            // TODO ��ͼƬѹ�������߽�ѹ

        }
        return true;
    }

    /**
     +----------------------------------------------------------
     * �ϴ��ļ�
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $savePath  �ϴ��ļ�����·��
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     * @throws ThinkExecption
     +----------------------------------------------------------
     */
    public function upload($savePath ='')
    {
        //�����ָ�������ļ���������ϵͳĬ��
        if(empty($savePath))
            $savePath = $this->savePath;
        // ����ϴ�Ŀ¼
        if(!is_dir($savePath)) {
            // ���Ŀ¼�Ƿ������
            if(is_dir(base64_decode($savePath))) {
                $savePath	=	base64_decode($savePath);
            }else{
                // ���Դ���Ŀ¼
                if(!mkdir($savePath)){
                    $this->error  =  '�ϴ�Ŀ¼'.$savePath.'������';
                    return false;
                }
            }
        }else {
            if(!is_writeable($savePath)) {
                $this->error  =  '�ϴ�Ŀ¼'.$savePath.'����д';
                return false;
            }
        }
        $fileInfo = array();
        $isUpload   = false;

        // ��ȡ�ϴ����ļ���Ϣ
        // ��$_FILES������Ϣ����
        $files	 =	 $this->dealFiles($_FILES);
        foreach($files as $key => $file) {
            //������Ч���ϴ�
            if(!empty($file['name'])) {
                //�Ǽ��ϴ��ļ�����չ��Ϣ
                $file['key']          =  $key;
                $file['extension']  = $this->getExt($file['name']);
                $file['savepath']   = $savePath;
                $file['savename']   = $this->getSaveName($file);

                // �Զ���鸽��
                if($this->autoCheck) {
                    if(!$this->check($file))
                        return false;
                }

                //�����ϴ��ļ�
                if(!$this->save($file)) return false;
				/*
                if(function_exists($this->hashType)) {
                    $fun =  $this->hashType;
                    $file['hash']   =  $fun(auto_charset($file['savepath'].$file['savename'],'utf-8','gbk'));
                }
				*/
                //�ϴ��ɹ��󱣴��ļ���Ϣ���������ط�����
                unset($file['tmp_name'],$file['error']);
                $fileInfo[] = $file;
                $isUpload   = true;
            }
        }
        if($isUpload) {
            $this->uploadFileInfo = $fileInfo;
            return true;
        }else {
            $this->error  =  'û��ѡ���ϴ��ļ�';
            return false;
        }
    }

    /**
     +----------------------------------------------------------
     * ת���ϴ��ļ��������Ϊ��ȷ�ķ�ʽ
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param array $files  �ϴ����ļ�����
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    private function dealFiles($files) {
       $fileArray = array();
       foreach ($files as $file){
           if(is_array($file['name'])) {
               $keys = array_keys($file);
               $count	 =	 count($file['name']);
               for ($i=0; $i<$count; $i++) {
                   foreach ($keys as $key)
                       $fileArray[$i][$key] = $file[$key][$i];
               }
           }else{
               $fileArray	=	$files;
           }
           break;
       }
       return $fileArray;
    }

    /**
     +----------------------------------------------------------
     * ��ȡ���������Ϣ
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @param string $errorNo  �������
     +----------------------------------------------------------
     * @return void
     +----------------------------------------------------------
     * @throws ThinkExecption
     +----------------------------------------------------------
     */
    protected function error($errorNo)
    {
         switch($errorNo) {
            case 1:
                $this->error = '�ϴ����ļ������� php.ini �� upload_max_filesize ѡ�����Ƶ�ֵ';
                break;
            case 2:
                $this->error = '�ϴ��ļ��Ĵ�С������ HTML ���� MAX_FILE_SIZE ѡ��ָ����ֵ';
                break;
            case 3:
                $this->error = '�ļ�ֻ�в��ֱ��ϴ�';
                break;
            case 4:
                $this->error = 'û���ļ����ϴ�';
                break;
            case 6:
                $this->error = '�Ҳ�����ʱ�ļ���';
                break;
            case 7:
                $this->error = '�ļ�д��ʧ��';
                break;
            default:
                $this->error = 'δ֪�ϴ�����';
        }
        return ;
    }

    /**
     +----------------------------------------------------------
     * �����ϴ��ļ���������ȡ�ñ����ļ���
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param string $filename ����
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    private function getSaveName($filename)
    {
        $saveName = date("YmdHis")."_".md5(rand(100000, 999999).'_chaomi').rand(100000, 999999).md5(time()+rand(100000, 999999)).'.'.$filename['extension'];
        // if($this->autoSub) {
            // ʹ����Ŀ¼�����ļ�
            // $saveName   =  $this->getSubName($filename).'/'.$saveName;
        // }
        return $saveName;
    }

    /**
     +----------------------------------------------------------
     * ��ȡ��Ŀ¼������
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param array $file  �ϴ����ļ���Ϣ
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    private function getSubName($file)
    {
        switch($this->subType) {
            case 'date':
                $dir   =  date($this->dateFormat,time());
                break;
            case 'hash':
            default:
                $name = md5($file['savename']);
                $dir   =  '';
                for($i=0;$i<$this->hashLevel;$i++) {
                    $dir   .=  $name{0}.'/';
                }
                break;
        }
        if(!is_dir($file['savepath'].$dir)) {
            mkdir($file['savepath'].$dir);
        }
        return $dir;
    }

    /**
     +----------------------------------------------------------
     * ����ϴ����ļ�
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param array $file �ļ���Ϣ
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function check($file) {
        if($file['error']!== 0) {
            //�ļ��ϴ�ʧ��
            //����������
            $this->error($file['error']);
            return false;
        }

        //����ļ�Mime����
        if(!$this->checkType($file['type'])) {
            $this->error = '�ϴ��ļ�MIME���Ͳ�����';
            return false;
        }
        //����ļ�����
        if(!$this->checkExt($file['extension'])) {
            $this->error ='�ϴ��ļ����Ͳ�����';
            return false;
        }
        //�ļ��ϴ��ɹ��������Զ��������
        //����ļ���С
        if(!$this->checkSize($file['size'])) {
            $this->error = '�ϴ��ļ���С�������ƣ�';
            return false;
        }

        //����Ƿ�Ϸ��ϴ�
        if(!$this->checkUpload($file['tmp_name'])) {
            $this->error = '�Ƿ��ϴ��ļ���';
            return false;
        }
        return true;
    }

    /**
     +----------------------------------------------------------
     * ����ϴ����ļ������Ƿ�Ϸ�
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param string $type ����
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function checkType($type)
    {
        if(!empty($this->allowTypes))
            return in_array(strtolower($type),$this->allowTypes);
        return true;
    }


    /**
     +----------------------------------------------------------
     * ����ϴ����ļ���׺�Ƿ�Ϸ�
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param string $ext ��׺��
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function checkExt($ext)
    {
        if(!empty($this->allowExts))
            return in_array(strtolower($ext),$this->allowExts,true);
        return true;
    }

    /**
     +----------------------------------------------------------
     * ����ļ���С�Ƿ�Ϸ�
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param integer $size ����
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function checkSize($size)
    {
        return !($size > $this->maxSize) || (-1 == $this->maxSize);
    }

    /**
     +----------------------------------------------------------
     * ����ļ��Ƿ�Ƿ��ύ
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param string $filename �ļ���
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function checkUpload($filename)
    {
        return is_uploaded_file($filename);
    }

    /**
     +----------------------------------------------------------
     * ȡ���ϴ��ļ��ĺ�׺
     +----------------------------------------------------------
     * @access private
     +----------------------------------------------------------
     * @param string $filename �ļ���
     +----------------------------------------------------------
     * @return boolean
     +----------------------------------------------------------
     */
    private function getExt($filename)
    {
        $pathinfo = pathinfo($filename);
        return $pathinfo['extension'];
    }

    /**
     +----------------------------------------------------------
     * ȡ���ϴ��ļ�����Ϣ
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return array
     +----------------------------------------------------------
     */
    public function getUploadFileInfo()
    {
        return $this->uploadFileInfo;
    }

    /**
     +----------------------------------------------------------
     * ȡ�����һ�δ�����Ϣ
     +----------------------------------------------------------
     * @access public
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function getErrorMsg()
    {
        return $this->error;
    }

}//�ඨ�����
?>