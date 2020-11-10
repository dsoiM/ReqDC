<?php

final class MainAPITest extends InitTests {


    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostJSONNoUser() {

        $this->expectOutputRegex('/userId or password incorrect/is');
        $_SERVER['MOCKUPHEADERS']['CONTENT-TYPE'] = 'JSON';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/'.TestConfig::testTenantID.'/'.TestConfig::implementationTestID;
        $x = new MainAPI();
        $x->handle();
    }


    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostJSONScheduled() {
        $this->expectOutputRegex('/"requestId":"\w{24}"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '[{"_id":"5d1122f225e1ad26f276c201","index":0,"guid":"87697b97-e247-4093-9ed2-e37c6bbb60a7","isActive":true,"balance":"$3,787.42","picture":"http://placehold.it/32x32","age":40,"eyeColor":"brown","name":"Walls Short","gender":"male","company":"SNACKTION","email":"wallsshort@snacktion.com","phone":"+1 (942) 502-3121","address":"264 Wallabout Street, Edmund, Oklahoma, 2756","about":"Labore reprehenderit cillum commodo tempor. Laboris non nulla mollit dolore nulla consectetur nisi ea excepteur. Dolore id minim duis adipisicing. Labore magna quis ullamco eu eu veniam exercitation. Nulla cillum et veniam velit non deserunt fugiat ex aliquip sit nisi tempor. Culpa labore minim ipsum et tempor culpa Lorem non mollit magna ad.\r\n","registered":"2017-02-20T11:12:56 -02:00","latitude":-84.779766,"longitude":-174.775291,"tags":["do","dolore","pariatur","labore","aliquip","qui","est"],"friends":[{"id":0,"name":"Haley Lyons"},{"id":1,"name":"Florence Tate"},{"id":2,"name":"Latonya Lucas"}],"greeting":"Hello, Walls Short! You have 7 unread messages.","favoriteFruit":"apple"},{"_id":"5d1122f2a96159b4d9f72bf2","index":1,"guid":"63f4f404-be8a-44ca-812d-47d386af69ec","isActive":false,"balance":"$1,603.75","picture":"http://placehold.it/32x32","age":26,"eyeColor":"brown","name":"Maxine Morgan","gender":"female","company":"VINCH","email":"maxinemorgan@vinch.com","phone":"+1 (978) 481-2223","address":"173 Ingraham Street, Hessville, Virgin Islands, 448","about":"Voluptate ea eu tempor culpa quis magna. Ipsum ea proident consequat laborum aliqua eu deserunt eiusmod officia consectetur labore officia commodo. Reprehenderit incididunt deserunt dolore sunt aute amet. In enim incididunt irure sit. Reprehenderit et est commodo amet elit aliquip consectetur amet nostrud quis ullamco consequat culpa.\r\n","registered":"2018-10-09T09:38:46 -03:00","latitude":47.22804,"longitude":178.810082,"tags":["reprehenderit","excepteur","elit","eu","nostrud","esse","ea"],"friends":[{"id":0,"name":"Hoover Clay"},{"id":1,"name":"Ana Cross"},{"id":2,"name":"Case Rollins"}],"greeting":"Hello, Maxine Morgan! You have 4 unread messages.","favoriteFruit":"strawberry"},{"_id":"5d1122f2751d5303db382683","index":2,"guid":"ada540d1-1a97-43ae-a421-5d9def9f6a33","isActive":true,"balance":"$3,860.88","picture":"http://placehold.it/32x32","age":39,"eyeColor":"brown","name":"Torres Ingram","gender":"male","company":"COMBOGENE","email":"torresingram@combogene.com","phone":"+1 (857) 554-3320","address":"710 Woodruff Avenue, Coyote, Delaware, 7052","about":"Esse ex exercitation in ex reprehenderit adipisicing reprehenderit aute esse id enim. Ut laborum nisi exercitation esse non sint sit cupidatat velit. Occaecat aute elit officia quis exercitation tempor incididunt eiusmod nulla labore culpa enim veniam. Pariatur magna irure nisi voluptate ex est aute do in nostrud. Aliqua labore ullamco irure excepteur est labore ut ut.\r\n","registered":"2015-12-09T06:26:30 -02:00","latitude":3.140935,"longitude":-71.91648,"tags":["nisi","nostrud","cupidatat","consectetur","labore","ut","consectetur"],"friends":[{"id":0,"name":"Maynard Parker"},{"id":1,"name":"Gracie Kane"},{"id":2,"name":"Cheryl Molina"}],"greeting":"Hello, Torres Ingram! You have 6 unread messages.","favoriteFruit":"banana"},{"_id":"5d1122f2029114a39c72ea53","index":3,"guid":"490d0b94-4372-477f-bfb1-258e5cd8c3ab","isActive":true,"balance":"$3,455.21","picture":"http://placehold.it/32x32","age":21,"eyeColor":"blue","name":"Smith James","gender":"male","company":"DIGITALUS","email":"smithjames@digitalus.com","phone":"+1 (856) 560-2820","address":"969 Bancroft Place, Graniteville, New Jersey, 7618","about":"Commodo laboris et velit cillum adipisicing id id enim id magna proident deserunt. Minim occaecat non et reprehenderit commodo tempor enim est eu. Anim Lorem cillum proident aliquip. Consequat magna id ullamco incididunt aute occaecat qui aliqua irure labore. Quis velit officia exercitation ipsum pariatur ut enim. Cupidatat sunt ullamco aliqua adipisicing eiusmod velit et nisi.\r\n","registered":"2016-05-11T09:44:30 -03:00","latitude":-83.911585,"longitude":-140.422635,"tags":["minim","anim","enim","nisi","dolor","deserunt","sit"],"friends":[{"id":0,"name":"Dorsey Jenkins"},{"id":1,"name":"Isabelle Alvarado"},{"id":2,"name":"Francesca Hogan"}],"greeting":"Hello, Smith James! You have 8 unread messages.","favoriteFruit":"banana"},{"_id":"5d1122f2ce53db8816d15dcb","index":4,"guid":"facfd40a-ac97-42ec-b08a-b835110a138c","isActive":true,"balance":"$2,503.36","picture":"http://placehold.it/32x32","age":22,"eyeColor":"green","name":"Hale Golden","gender":"male","company":"GENMY","email":"halegolden@genmy.com","phone":"+1 (887) 560-3211","address":"511 Stockholm Street, Weedville, Vermont, 1093","about":"Enim in id anim excepteur duis ullamco laboris est quis voluptate id est. Aute commodo magna eu ea irure quis ea eiusmod minim laboris mollit commodo. Eu cupidatat irure occaecat minim voluptate excepteur ad cillum. Aute quis magna anim anim in duis nulla qui amet.\r\n","registered":"2015-11-15T09:52:04 -02:00","latitude":-13.30723,"longitude":-177.953573,"tags":["ullamco","cupidatat","esse","pariatur","pariatur","esse","cillum"],"friends":[{"id":0,"name":"Kendra Mclaughlin"},{"id":1,"name":"Jordan Wilkins"},{"id":2,"name":"Jeri Brock"}],"greeting":"Hello, Hale Golden! You have 10 unread messages.","favoriteFruit":"banana"},{"_id":"5d1122f275a127a03be40b4c","index":5,"guid":"3eff724c-13c0-4853-970d-d642e109f0d2","isActive":false,"balance":"$3,543.73","picture":"http://placehold.it/32x32","age":20,"eyeColor":"green","name":"Wilkins Hart","gender":"male","company":"PROVIDCO","email":"wilkinshart@providco.com","phone":"+1 (841) 501-2892","address":"250 Mill Road, Hatteras, West Virginia, 2699","about":"Eu ut laborum ullamco cillum incididunt ut consectetur elit amet pariatur irure. Pariatur quis aute amet est occaecat magna anim nulla id velit fugiat reprehenderit culpa. Incididunt eiusmod veniam commodo cupidatat quis incididunt cupidatat duis est excepteur qui. Ut laborum irure tempor voluptate. Incididunt ullamco proident nisi culpa.\r\n","registered":"2015-04-21T03:03:57 -03:00","latitude":52.843676,"longitude":102.684113,"tags":["proident","exercitation","est","ex","ipsum","consectetur","excepteur"],"friends":[{"id":0,"name":"Robbie Diaz"},{"id":1,"name":"Burke Burris"},{"id":2,"name":"Flowers Blanchard"}],"greeting":"Hello, Wilkins Hart! You have 4 unread messages.","favoriteFruit":"banana"},{"_id":"5d1122f219f5628f0cf757a6","index":6,"guid":"ad225c18-f896-4ccb-907d-520361113eb5","isActive":false,"balance":"$3,951.36","picture":"http://placehold.it/32x32","age":29,"eyeColor":"brown","name":"Rosales Weiss","gender":"male","company":"JUNIPOOR","email":"rosalesweiss@junipoor.com","phone":"+1 (839) 519-2599","address":"275 Lefferts Place, Belgreen, Georgia, 7239","about":"Aliqua sit ipsum cupidatat ea. Lorem magna sunt anim magna commodo ad tempor ipsum est magna sunt magna adipisicing. Anim eu duis cupidatat elit excepteur incididunt cillum sint deserunt. Nostrud est officia labore anim minim dolor. Ullamco non ea pariatur irure officia.\r\n","registered":"2017-02-04T10:22:15 -02:00","latitude":-59.200013,"longitude":-156.362501,"tags":["eiusmod","pariatur","pariatur","sunt","exercitation","fugiat","nisi"],"friends":[{"id":0,"name":"Reyes Crosby"},{"id":1,"name":"Esmeralda Hudson"},{"id":2,"name":"Roslyn Sanford"}],"greeting":"Hello, Rosales Weiss! You have 7 unread messages.","favoriteFruit":"apple"}]';
        $x = new MainAPI();
        $x->handle();
        sleep(3);

        $this->assertRegExp('/\w{24}/', $x->getRequest()->getId()->__toString());
        $result = Execution::getAllByRequestId($x->getRequest()->getId());
        $this->assertCount(1, $result,'Could not find any execution by request ID '. $x->getRequest()->getId()->__toString());
        $this->assertInstanceOf('Execution', $result[0]);
        $this->assertRegExp('/\w{24}/', $result[0]->getId()->__toString());

    }

    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostJSONSynchronousWrongDataAndHaltableException() {

        $this->expectOutputRegex('/"ABC field did not exist in request JSON body"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '[1,2]';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
    }

    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostJSONSynchronousWithCustomResponse() {

        $this->expectOutputRegex('/"requestId":"\w{24}","executionId":"\w{24}.*thisberespondedcustomstring"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '{"abc":1,"respondsome":1}';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
    }

    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostXMLSynchronousWrongDataAndHaltableException() {

        $this->expectOutputRegex('/<errorMessage>ABC field did not exist in request JSON body<\/errorMessage>/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPHEADERS']['CONTENT-TYPE'] = 'XML';
        $_SERVER['MOCKUPBODY'] = '<root/>';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
    }
    
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostXMLSynchronousItem() {
        
        $this->expectOutputRegex('/<xmlitem>abyarvo<\/xmlitem>/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPHEADERS']['CONTENT-TYPE'] = 'XML';
        $_SERVER['MOCKUPBODY'] = '<root><item>abyarvo</item></root>';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
    }


    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testMainAPIPostJSONSynchronousWrongDataAndSevereException() {

        $this->expectOutputRegex('/,"errorCode":500.*requestId.*executionId/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '{"makeBigMistake":1}';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
    }


}