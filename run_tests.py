#!/usr/bin/env python3
"""
Run Tests Script
---------------
Este script executa testes automatizados em um ambiente Docker
com banco de dados pré-populado para o sistema de pagamento multi-gateway.
"""

import os
import sys
import subprocess
import time


# Cores para formatação no terminal
class Colors:
    """Define cores para saídas no terminal."""
    RED = '\033[0;31m'
    GREEN = '\033[0;32m'
    YELLOW = '\033[0;33m'
    BLUE = '\033[0;34m'
    NC = '\033[0m'  # No Color


def log_info(message):
    """Exibe mensagem informativa."""
    print(f"{Colors.BLUE}[INFO]{Colors.NC} {message}")


def log_success(message):
    """Exibe mensagem de sucesso."""
    print(f"{Colors.GREEN}[SUCCESS]{Colors.NC} {message}")


def log_warning(message):
    """Exibe mensagem de aviso."""
    print(f"{Colors.YELLOW}[AVISO]{Colors.NC} {message}")


def log_error(message):
    """Exibe mensagem de erro."""
    print(f"{Colors.RED}[ERRO]{Colors.NC} {message}")


def run_command(command, env=None, check=True):
    """
    Executa um comando do sistema e retorna o resultado.

    Args:
        command: Comando a ser executado.
        env: Variáveis de ambiente para o comando.
        check: Se deve levantar exceção em caso de erro.

    Returns:
        Objeto CompletedProcess com os resultados do comando.
    """
    try:
        # Mesclar variáveis de ambiente existentes com as fornecidas
        merged_env = os.environ.copy()
        if env:
            merged_env.update(env)

        result = subprocess.run(
            command,
            shell=True,
            check=check,
            text=True,
            env=merged_env,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE
        )
        print(result.stdout)
        if result.stderr:
            print(result.stderr)
        return result
    except subprocess.CalledProcessError as e:
        print(e.stdout)
        print(e.stderr)
        if check:
            raise
        return e


def find_docker_compose_command():
    """
    Determina o comando adequado para o Docker Compose.

    Returns:
        String com o comando docker-compose ou docker compose.
    """
    # Verificar se docker-compose está disponível
    try:
        subprocess.run(
            "docker-compose --version",
            shell=True,
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )
        return "docker-compose"
    except subprocess.CalledProcessError:
        # Verificar formato mais recente (docker compose)
        try:
            subprocess.run(
                "docker compose version",
                shell=True,
                check=True,
                stdout=subprocess.DEVNULL,
                stderr=subprocess.DEVNULL
            )
            return "docker compose"
        except subprocess.CalledProcessError:
            log_error(
                "Docker Compose não encontrado. Por favor, instale o Docker Compose.")
            sys.exit(1)


def check_env_testing_file():
    """Verifica se o arquivo .env.testing existe."""
    if not os.path.isfile(".env.testing"):
        log_error("Arquivo .env.testing não encontrado!")
        sys.exit(1)
    return True


def check_containers_running(docker_compose, app_service):
    """
    Verifica se os contêineres estão rodando, e inicia se necessário.

    Args:
        docker_compose: Comando do docker-compose
        app_service: Nome do serviço da aplicação
    """
    cmd = f"{docker_compose} ps --services --filter \"status=running\""
    result = run_command(cmd, check=False)

    if app_service not in result.stdout:
        log_warning("Contêiner da aplicação não está em execução. Iniciando...")
        run_command(f"{docker_compose} up -d")
        log_success("Contêineres iniciados.")
        time.sleep(5)  # Esperar os contêineres inicializarem


def setup_test_database(docker_compose, db_test_service):
    """
    Configura o banco de dados de teste.

    Args:
        docker_compose: Comando do docker-compose
        db_test_service: Nome do serviço do banco de teste
    """
    log_info("Configurando banco de dados de teste...")

    # Variáveis do banco de teste
    db_name = "multigateway_test"
    db_user = "multigateway_test"
    db_pass = "test_password"

    # Comandos SQL para configurar o banco
    db_commands = [
        f"CREATE DATABASE IF NOT EXISTS {db_name};",
        f"CREATE USER IF NOT EXISTS '{db_user}'@'%' IDENTIFIED BY '{db_pass}';",
        f"GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'%';",
        "FLUSH PRIVILEGES;"
    ]

    # Executar cada comando SQL
    for sql_cmd in db_commands:
        cmd = f"{docker_compose} exec {db_test_service} mysql -u root -proot_password -e \"{sql_cmd}\""
        run_command(cmd)


def prepare_laravel_environment(docker_compose, app_service):
    """
    Prepara o ambiente Laravel para testes limpando caches.

    Args:
        docker_compose: Comando do docker-compose
        app_service: Nome do serviço da aplicação
    """
    log_info("Limpando caches e preparando ambiente de teste...")

    cache_commands = [
        "php artisan config:clear",
        "php artisan route:clear",
        "php artisan cache:clear"
    ]

    for cmd in cache_commands:
        run_command(f"{docker_compose} exec {app_service} {cmd}")


def run_migrations(docker_compose, app_service, db_test_params):
    """
    Executa migrações e seeders no banco de teste.

    Args:
        docker_compose: Comando do docker-compose
        app_service: Nome do serviço da aplicação
        db_test_params: Parâmetros do banco de teste
    """
    log_info("Executando migrações e seeders no banco de teste...")

    env_params = " ".join([f"-e {k}={v}" for k, v in db_test_params.items()])

    cmd = f"{docker_compose} exec {env_params} {app_service} php artisan migrate:fresh --seed --env=testing"
    run_command(cmd)

    log_success("Banco de testes preparado com dados de seed!")


def run_tests(docker_compose, app_service, db_test_params, test_args=""):
    """
    Executa os testes.

    Args:
        docker_compose: Comando do docker-compose
        app_service: Nome do serviço da aplicação
        db_test_params: Parâmetros do banco de teste
        test_args: Argumentos adicionais para os testes

    Returns:
        Código de resultado dos testes
    """
    log_info("Executando testes...")

    # Adicionar variável para sinalizar uso de seeds
    db_test_params["RUN_SEEDS_FOR_TESTS"] = "true"

    env_params = " ".join([f"-e {k}={v}" for k, v in db_test_params.items()])

    cmd = f"{docker_compose} exec {env_params} {app_service} php artisan test {test_args}"

    try:
        result = run_command(cmd)
        return 0  # Sucesso
    except subprocess.CalledProcessError as e:
        return e.returncode  # Falha


def main():
    """Função principal do script."""
    print(f"{Colors.BLUE}=== EXECUTANDO TESTES COM BANCO DE DADOS PRÉ-POPULADO ==={Colors.NC}\n")

    # Encontrar o comando Docker Compose
    docker_compose = find_docker_compose_command()

    # Definir nomes dos serviços
    app_service = "app"
    db_test_service = "db_test"

    # Verificar arquivo .env.testing
    check_env_testing_file()

    # Verificar contêineres
    check_containers_running(docker_compose, app_service)

    # Configurar banco de testes
    setup_test_database(docker_compose, db_test_service)

    # Limpar caches e preparar ambiente
    prepare_laravel_environment(docker_compose, app_service)

    # Definir parâmetros do banco de teste
    db_test_params = {
        "DB_CONNECTION": "mysql",
        "DB_HOST": db_test_service,
        "DB_DATABASE": "multigateway_test",
        "DB_USERNAME": "multigateway_test",
        "DB_PASSWORD": "test_password"
    }

    # Executar migrações
    run_migrations(docker_compose, app_service, db_test_params)

    # Capturar argumentos extras passados para o script
    test_args = " ".join(sys.argv[1:])

    # Executar testes
    test_result = run_tests(docker_compose, app_service,
                            db_test_params, test_args)

    # Verificar resultado
    if test_result == 0:
        print(
            f"\n{Colors.GREEN}[SUCCESS]{Colors.NC} Todos os testes passaram com sucesso!")
    else:
        print(
            f"\n{Colors.RED}[ERROR]{Colors.NC} Alguns testes falharam. Verifique os logs acima.")

    print(f"\n{Colors.BLUE}=== FIM DOS TESTES COM BANCO PRÉ-POPULADO ==={Colors.NC}")
    sys.exit(test_result)


if __name__ == "__main__":
    main()
