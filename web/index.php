<?php
use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../vendor/autoload.php';

$capsule = new Capsule;

//Exemplo mysql
$capsule->addConnection([
   "driver"     => "pgsql",
   "host"       => "server",
   "database"   => "db",
   "username"   => "user",
   "password"   => "pwd",
   "charset"    => "utf8",
   "collation"  => "utf8_general_ci"
]);

// Define o dispatcher usado pelos models do Eloquent (opcional)
//use Illuminate\Events\Dispatcher;
//use Illuminate\Container\Container;
//$capsule->setEventDispatcher(new Dispatcher(new Container));

// Faz essa instancia de Capsule ficar disponível globalmente usando metodos estaticos (opcional)
$capsule->setAsGlobal();

// Configura o Eloquent ORM... (opcional e desnecessário se você já usou setEventDispatcher())
//$capsule->bootEloquent();

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Silex\Application;

$app = new Silex\Application();

$app['debug'] = true;

// Autenticacao
$app->post('/auth', function (Request $request) use ($app) {
    $dados = json_decode($request->getContent(), true);

    if($dados['user'] == 'foo' && $dados['pass'] == 'bar') {
        // autenticacao valida, gerar token
        $jwt = JWTWrapper::encode([
            'expiration_sec' => 3600,
            'iss' => 'gorkyandre.com.br',
            'userdata' => [
                'id' => 1,
                'name' => 'Gorky Velasquez'
            ]
        ]);

        return $app->json([
            'login' => 'true',
            'access_token' => $jwt
        ]);
    }

    return $app->json([
        'login' => 'false',
        'message' => 'Login Inválido',
    ]);
});

// verificar autenticacao
/*
$app->before(function(Request $request, Application $app) {
    $route = $request->get('_route');

    if($route != 'POST_auth') {
        $authorization = $request->headers->get("Authorize");
        list($jwt) = sscanf($authorization, 'Bearer %s');

        if($jwt) {
            try {
                $app['jwt'] = JWTWrapper::decode($jwt);
            } catch(Exception $ex) {
                // nao foi possivel decodificar o token jwt
                return new Response('Acesso nao autorizado', 400);
            }

        } else {
            // nao foi possivel extrair token do header Authorization
            return new Response('Token nao informado', 400);
        }
    }
});
*/

$app->get('/', function () use ($app) {
    return new Response('WS Pós', 200);
});

$app->get('/medicos', function () use ($app) {
    $output = '';

    //$profissionais = Capsule::table('profissionais')->where('ativo', '=', 'S')->get();
    $sql  = "select ";
    $sql .= "trim(pro.crm) as Crm, pro.codprofissional as Cod, trim(pro.profissional) as Nome, ";
    $sql .= "pro.especialidade as Especialidade, uni.unidade as Unidade ";
    $sql .= "from profissionais as pro ";
    $sql .= "inner join profissionaluni as uni ";
    $sql .= "on uni.codprofissionaluni = pro.codprofissional ";
    $sql .= "where pro.ativo = ? ";
    $profissionais = Capsule::select($sql, array("S"));


    /*
    foreach ($alunos as $aluno) {
      //print_r($aluno);
        $output .= $aluno->ALU_NOME;
        $output .= '<br />';
    }
    */

    // Retorna os valores em json
    $output = new \Symfony\Component\HttpFoundation\JsonResponse();
    $output->setEncodingOptions(JSON_NUMERIC_CHECK);
    $output->setData(array('profissionais' => $profissionais));

    return $output;
});


$app->get('/convenios', function () use ($app) {
    $output = '';

    $sql  = "select ";
    $sql .= "codconvenio as Codigo, trim(convenio) as Descricao ";
    $sql .= "from convenios ";
    $sql .= "order by convenio ";
    $parceiros = Capsule::select($sql);

    // Retorna os valores em json
    $output = new \Symfony\Component\HttpFoundation\JsonResponse();
    $output->setEncodingOptions(JSON_NUMERIC_CHECK);
    $output->setData(array('parceiros' => $parceiros));

    return $output;
});

$app->get('/procedimentos', function () use ($app) {
    $output = '';

    $sql  = "select ";
    $sql .= "trim(tabop_codprocedimento) as Codigo, trim(tabop_descricaoprocedimento) as Descricao, ";
    $sql .= "tabop_especialidade as Especialidade, tabop_unidade as Unidade ";
    $sql .= "from tabelasoperadoras ";
    $sql .= "GROUP BY tabop_codprocedimento, tabop_descricaoprocedimento, tabop_especialidade, tabop_unidade ";
    $sql .= "order by tabop_especialidade, tabop_unidade,tabop_descricaoprocedimento ";
    $procedimento = Capsule::select($sql);

    // Retorna os valores em json
    $output = new \Symfony\Component\HttpFoundation\JsonResponse();
    $output->setEncodingOptions(JSON_NUMERIC_CHECK);
    $output->setData(array('procedimento' => $procedimento));

    return $output;
});

$app->get('/escala-medico', function () use ($app) {
    $output = '';

    $dia = date('d');
    $Mes = date('m');
    $Ano = date('Y');
    $numero = cal_days_in_month(CAL_GREGORIAN, $Mes, $Ano);

    if(empty($_GET['unidade'])){
      $unidade=1;
    }
    else{
      $unidade = $_GET['unidade'];
    }

    $status='N';

    $sql  = "select ";
    $sql .= "medico, data, status, unidade, especialidade, reservado, horario, ";
    $sql .= "CASE WHEN reservado = 'B' THEN 'SIM' ELSE 'NÃO' END as bloqueado ";
    $sql .= "from agendamedicobioclinica ";
    $sql .= "where unidade = ? AND status = ? AND data between '$dia/$Mes/$Ano' ";
    $sql .= "AND '$numero/$Mes/$Ano' order by data, medico ";
    $escala = Capsule::select($sql, array($unidade, $status));

    // Retorna os valores em json
    $output = new \Symfony\Component\HttpFoundation\JsonResponse();
    $output->setEncodingOptions(JSON_NUMERIC_CHECK);
    $output->setData(array('escala' => $escala));

    return $output;
});

$app->run();