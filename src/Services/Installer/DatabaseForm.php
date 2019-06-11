<?php
namespace Exceedone\Exment\Services\Installer;

use Exceedone\Exment\Model\Define;

/**
 * 
 */
class DatabaseForm
{
    use InstallFormTrait;

    protected $database_default = null;

    protected const settings = [
        'connection',
        'host',
        'port',
        'database',
        'username',
        'password',
    ];
    
    public function index(){

        $database_default = config('database.default', 'mysql');
        $database_connection = config("database.connections.$database_default");

        $args = [
            'connection_options' => ['mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'sqlsrv' => 'SQL Server (β)'],
            'connection_default' => $database_default,
        ];

        foreach(static::settings as $s){
            $args[$s] = array_get($database_connection, $s);
        }

        return view('exment::install.database', $args);
    }
    
    public function post(){
        $request = request();

        $rules = [];
        foreach(static::settings as $s){
            $rules[$s] = 'required';
        }
        
        $validation = \Validator::make($request->all(), $rules);
        if ($validation->fails()) {
            return back()->withInput()->withErrors($validation);
        }

        if(!$this->canDatabaseConnection($request)){
            return back()->withInput()->withErrors([
                'database_canconnection' => exmtrans('install.error.database_canconnection'),
            ]);
        }
        
        $inputs = [];
        foreach(static::settings as $s){
            $inputs['DB_' . strtoupper($s)] = $request->get($s);
        }
        $inputs[Define::ENV_EXMENT_INITIALIZE] = 1;

        $this->setEnv($inputs);

        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');

        return redirect(admin_url('install'));
    }

    /**
     * Check Database Connection
     *
     * @param [type] $request
     * @return boolean is connect database
     */
    protected function canDatabaseConnection($request){
        $inputs = $request->all(static::settings);
        // check connection
        $database_default = $inputs['connection'];
        $this->database_default = $database_default;

        $newConfig = config("database.connections.$database_default");
        $newConfig = array_merge($newConfig, $inputs);

        // set config
        config(["database.connections.$database_default" =>  $newConfig]);

        try{
            \DB::reconnect($database_default);
        }
        catch (\Exception $exception) {
            return false;
        }

        return \DB::canConnection();
    }

    protected function checkDatabaseVersion(){
        \DB::getVersion();
    }
}
