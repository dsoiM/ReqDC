<?php
final class DataStorageTest extends InitTests
{

    /**
     * Change pw to garble and then change it back to what is defined in config
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSettingAndGettingValues() {

        DataStorage::set('UnittestCategory', 'testkeystring', 'testvaluestring');
        $val = DataStorage::get('UnittestCategory', 'testkeystring');
        $this->assertEquals($val,'testvaluestring');
        DataStorage::delete('UnittestCategory', 'testkeystring');
        $this->expectExceptionMessageMatches('/.*not found.*/is');
        $val = DataStorage::get('UnittestCategory', 'testkeystring');
        
        $this->expectExceptionMessageMatches(null);
        //RESET
        
        DataStorage::set('UnittestCategory', 'testkeystring_encrypted', 'testvaluestring',true,'string_encrypted');
        $val = DataStorage::get('UnittestCategory', 'testkeystring');
        $this->assertEquals($val,'testvaluestring');
        DataStorage::delete('UnittestCategory', 'testkeystring');
        $this->expectExceptionMessageMatches('/.*not found.*/is');
        $val = DataStorage::get('UnittestCategory', 'testkeystring');
        
        
        
    }
    
    /**
     * Change pw to garble and then change it back to what is defined in config
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSettingAndGettingValuesEnc() {

        DataStorage::set('UnittestCategory', 'testkeystring_encrypted', 'testvaluestring_encrypted',true,'string_encrypted');
        $val = DataStorage::get('UnittestCategory', 'testkeystring_encrypted');
        $this->assertEquals($val,'testvaluestring_encrypted');
        DataStorage::delete('UnittestCategory', 'testkeystring_encrypted');
        $this->expectExceptionMessageMatches('/.*not found.*/is');
        $val = DataStorage::get('UnittestCategory', 'testkeystring_encrypted');
        
        
        
    }
    
    
}
