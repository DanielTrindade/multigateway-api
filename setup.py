#!/usr/bin/env python3
"""
Multi-Gateway Payment System Setup Tool - Portabilidade direta do setup.sh
Seguindo diretrizes PEP 8.
"""

import os
import sys
import subprocess
import time
import shutil


# Cores para formatação no terminal
class Colors:
    """Define cores para saídas no terminal."""
    RED = '\033[0;31m'
    GREEN = '\033[0;32m'
    YELLOW = '\033[0;33m'
    BLUE = '\033[0;34m'
    CYAN = '\033[1;36m'
    RESET = '\033[0m'  # No Color


def log_info(message):
    """Exibe mensagem informativa."""
    print(f"{Colors.BLUE}[INFO]{Colors.RESET} {message}")


def log_success(message):
    """Exibe mensagem de sucesso."""
    print(f"{Colors.GREEN}[SUCCESS]{Colors.RESET} {message}")


def log_warning(message):
    """Exibe mensagem de aviso."""
    print(f"{Colors.YELLOW}[WARNING]{Colors.RESET} {message}")


def log_error(message):
    """Exibe mensagem de erro."""
    print(f"{Colors.RED}[ERROR]{Colors.RESET} {message}")


def run_command(command):
    """
    Executa um comando e retorna o resultado.

    Args:
        command: String com o comando a ser executado

    Returns:
        bool: True se o comando foi executado com sucesso, False caso contrário
    """
    try:
        process = subprocess.run(command, shell=True, check=True, text=True)
        return process.returncode == 0
    except subprocess.CalledProcessError as e:
        if hasattr(e, 'stderr') and e.stderr:
            print(e.stderr)
        return False


def find_docker_compose():
    """
    Determina qual comando do Docker Compose usar.

    Returns:
        str: Comando do Docker Compose a ser usado ("docker-compose" ou "docker compose")
    """
    # Verificar se docker-compose está disponível
    docker_compose_cmd = subprocess.run(
        "command -v docker-compose",
        shell=True,
        capture_output=True,
        text=True,
        check=False  # Definido explicitamente
    )

    if docker_compose_cmd.returncode == 0:
        return "docker-compose"

    # Verificar formato mais recente (docker compose)
    docker_cmd = subprocess.run(
        "command -v docker",
        shell=True,
        capture_output=True,
        text=True,
        check=False  # Definido explicitamente
    )

    docker_compose_version = subprocess.run(
        "docker compose version",
        shell=True,
        capture_output=True,
        text=True,
        check=False  # Definido explicitamente
    )

    if docker_cmd.returncode == 0 and docker_compose_version.returncode == 0:
        return "docker compose"

    log_error("Docker Compose não encontrado. Por favor, instale o Docker Compose.")
    sys.exit(1)


def update_env_line(key, value, env_file):
    """
    Atualiza uma linha no arquivo .env.

    Args:
        key: Chave a ser atualizada
        value: Novo valor para a chave
        env_file: Caminho para o arquivo .env
    """
    log_info(f"Configurado {key}={value} em {env_file}")

    # Verificar se a linha já existe
    with open(env_file, "r") as f:
        lines = f.readlines()

    key_exists = False
    for i, line in enumerate(lines):
        if line.startswith(f"{key}="):
            # Substituir a linha existente
            lines[i] = f"{key}={value}\n"
            key_exists = True
            break

    # Adicionar a linha se não existir
    if not key_exists:
        lines.append(f"{key}={value}\n")

    # Escrever o arquivo atualizado
    with open(env_file, "w") as f:
        f.writelines(lines)


def check_app_readiness(docker_compose, max_attempts=15):
    """
    Verifica se a aplicação Laravel está pronta.

    Args:
        docker_compose: Comando do Docker Compose
        max_attempts: Número máximo de tentativas

    Returns:
        bool: True se a aplicação está pronta, False caso contrário
    """
    app_ready = False
    attempt = 1

    while attempt <= max_attempts:
        log_info(
            f"Verificando aplicação Laravel... (Tentativa {attempt} de {max_attempts})")

        php_check = subprocess.run(
            f"{docker_compose} exec app php -v",
            shell=True,
            capture_output=True,
            text=True,
            check=False  # Definido explicitamente
        )

        if php_check.returncode == 0:
            log_success("Aplicação está rodando!")
            app_ready = True
            break
        else:
            if attempt == max_attempts:
                log_error(
                    "Tempo limite excedido. A aplicação pode não estar funcionando corretamente.")
            else:
                log_warning("Aplicação ainda não está pronta. Aguardando...")
                time.sleep(8)
                attempt += 1

    return app_ready


def setup_laravel_directory():
    """
    Verifica e configura o diretório da aplicação Laravel.

    Returns:
        bool: True se o diretório foi criado, False caso contrário
    """
    if not os.path.exists("multigateway-app"):
        log_info(
            "O diretório 'multigateway-app' não foi encontrado! Criando o diretório...")
        os.makedirs("multigateway-app", exist_ok=True)

        # Verificar se o Composer está instalado
        composer_check = subprocess.run(
            "command -v composer",
            shell=True,
            capture_output=True,
            text=True,
            check=False  # Definido explicitamente
        )

        if composer_check.returncode == 0:
            log_info("Criando um novo projeto Laravel na pasta multigateway-app...")
            run_command(
                "composer create-project laravel/laravel multigateway-app")
            log_success("Projeto Laravel criado com sucesso!")
            return True
        else:
            log_warning(
                "Composer não encontrado no sistema. O diretório multigateway-app foi criado, mas você precisará instalar o Laravel manualmente.")
            log_info(
                "Recomendação: instale o Composer e execute 'composer create-project laravel/laravel multigateway-app' na raiz do projeto.")
            return True

    log_info("O diretório 'multigateway-app' já existe.")
    return False


def setup_env_files():
    """
    Configura os arquivos .env na raiz e no diretório Laravel.

    Returns:
        dict: Dicionário com as variáveis do arquivo .env
    """
    log_info("Configurando ambiente...")

    # Configurar arquivo .env na raiz
    if not os.path.exists(".env"):
        if os.path.exists(".env.example"):
            log_info("Criando arquivo .env a partir do .env.example...")
            shutil.copy(".env.example", ".env")
            log_success("Arquivo .env criado com sucesso.")
        else:
            log_warning(
                "Arquivo .env.example não encontrado na raiz do projeto. Criando .env básico...")
            with open(".env", "w") as env_file:
                env_file.write("""DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306

MYSQL_DATABASE=multigateway-db
MYSQL_USER=multigateway
MYSQL_PASSWORD=multigateway_password
MYSQL_ROOT_PASSWORD=root_password

GATEWAY1_URL=http://gateway1:3001
GATEWAY2_URL=http://gateway2:3002
GATEWAY1_EMAIL=dev@betalent.tech
GATEWAY1_TOKEN=FEC9BB078BF338F464F96B48089EB498
GATEWAY2_AUTH_TOKEN=tk_f2198cc671b5289fa856
GATEWAY2_AUTH_SECRET=3d15e8ed6131446ea7e3456728b1211f
""")
            log_success("Arquivo .env básico criado com sucesso.")
    else:
        log_info("Arquivo .env já existe na raiz do projeto.")

    # Configurar o .env do Laravel
    log_info("Configurando ambiente Laravel...")

    if not os.path.exists("multigateway-app/.env"):
        if os.path.exists("multigateway-app/.env.example"):
            log_info("Criando arquivo .env do Laravel a partir do .env.example...")
            shutil.copy("multigateway-app/.env.example",
                        "multigateway-app/.env")
        elif os.path.exists(".env"):
            log_info("Copiando .env da raiz para o diretório Laravel...")
            shutil.copy(".env", "multigateway-app/.env")
        else:
            log_error(
                "Não foi possível encontrar um arquivo .env.example para o Laravel.")
            sys.exit(1)
        log_success("Arquivo .env do Laravel configurado.")
    else:
        log_info("Arquivo .env do Laravel já existe.")

    # Carregar variáveis do .env
    env_vars = {}
    if os.path.exists(".env"):
        with open(".env", "r") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    env_vars[key] = value

    return env_vars


def sync_env_variables(env_vars):
    """
    Sincroniza variáveis de ambiente entre os arquivos .env.

    Args:
        env_vars: Dicionário com as variáveis do .env da raiz
    """
    log_info("Sincronizando variáveis de ambiente...")

    # Database
    update_env_line(
        "DB_CONNECTION",
        env_vars.get("DB_CONNECTION", "mysql"),
        "multigateway-app/.env"
    )
    update_env_line(
        "DB_HOST",
        env_vars.get("DB_HOST", "db"),
        "multigateway-app/.env"
    )
    update_env_line(
        "DB_PORT",
        env_vars.get("DB_PORT", "3306"),
        "multigateway-app/.env"
    )
    update_env_line(
        "DB_DATABASE",
        env_vars.get("DB_DATABASE", "multigateway-db"),
        "multigateway-app/.env"
    )
    update_env_line(
        "DB_USERNAME",
        env_vars.get("DB_USERNAME", "multigateway"),
        "multigateway-app/.env"
    )
    update_env_line(
        "DB_PASSWORD",
        env_vars.get("DB_PASSWORD", "multigateway_password"),
        "multigateway-app/.env"
    )

    # Gateway 1
    update_env_line(
        "GATEWAY1_URL",
        env_vars.get("GATEWAY1_URL", "http://gateway1:3001"),
        "multigateway-app/.env"
    )
    update_env_line(
        "GATEWAY1_EMAIL",
        env_vars.get("GATEWAY1_EMAIL", "dev@betalent.tech"),
        "multigateway-app/.env"
    )
    update_env_line(
        "GATEWAY1_TOKEN",
        env_vars.get("GATEWAY1_TOKEN", "FEC9BB078BF338F464F96B48089EB498"),
        "multigateway-app/.env"
    )

    # Gateway 2
    update_env_line(
        "GATEWAY2_URL",
        env_vars.get("GATEWAY2_URL", "http://gateway2:3002"),
        "multigateway-app/.env"
    )
    update_env_line(
        "GATEWAY2_AUTH_TOKEN",
        env_vars.get("GATEWAY2_AUTH_TOKEN", "tk_f2198cc671b5289fa856"),
        "multigateway-app/.env"
    )
    update_env_line(
        "GATEWAY2_AUTH_SECRET",
        env_vars.get("GATEWAY2_AUTH_SECRET",
                     "3d15e8ed6131446ea7e3456728b1211f"),
        "multigateway-app/.env"
    )


def create_nginx_config():
    """Cria a configuração do Nginx para o projeto."""
    log_info("Criando diretórios necessários...")
    os.makedirs("docker/nginx/conf.d", exist_ok=True)

    # Criar ou atualizar a configuração do Nginx
    nginx_config = "docker/nginx/conf.d/app.conf"
    if not os.path.exists(nginx_config):
        log_info("Criando configuração do Nginx...")
        with open(nginx_config, "w") as f:
            f.write("""server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html/public;
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }
    location / {
        try_files $uri $uri/ /index.php?$query_string;
        gzip_static on;
    }
}
""")
        log_success("Configuração do Nginx criada com sucesso.")


def check_existing_containers(docker_compose):
    """
    Verifica se já existem contêineres em execução.

    Args:
        docker_compose: Comando do Docker Compose
    """
    log_info("Verificando contêineres existentes...")

    ps_output = subprocess.run(
        f"{docker_compose} ps -q",
        shell=True,
        capture_output=True,
        text=True,
        check=False  # Definido explicitamente
    )

    if ps_output.stdout.strip():
        log_warning(
            "Contêineres existentes encontrados. Parando-os antes de continuar...")
        run_command(f"{docker_compose} down")
        log_success("Contêineres parados com sucesso.")


def get_clean_option():
    """
    Solicita ao usuário a opção de limpeza.

    Returns:
        tuple: (str, str) - Opção selecionada e flag para migração
    """
    print(f"{Colors.YELLOW}=====================================")
    print("      OPÇÕES DE LIMPEZA")
    print(f"====================================={Colors.RESET}")
    print("Escolha uma opção:")
    print("1. Manter todos os dados (recomendado para continuar desenvolvimento)")
    print("2. Limpar apenas dados do banco de dados (mantém volumes Docker)")
    print("3. Limpar todos os volumes Docker (ambiente totalmente novo)")

    clean_option = input("Digite o número da opção (1-3) [1]: ").strip() or "1"

    # Definir comportamento de migração com base na opção
    if clean_option == "1":
        log_info("Mantendo todos os dados existentes.")
        fresh_migrate = "no"
    elif clean_option == "2":
        log_info("Limpando apenas dados do banco de dados.")
        fresh_migrate = "yes"
    elif clean_option == "3":
        log_warning("Limpando todos os volumes Docker...")
        run_command("docker volume prune -f")
        log_success("Volumes limpos com sucesso.")
        fresh_migrate = "yes"
    else:
        log_warning(
            "Opção inválida. Usando opção padrão (1) - Manter todos os dados.")
        fresh_migrate = "no"

    return clean_option, fresh_migrate


def build_and_start_containers(docker_compose):
    """
    Constrói e inicia os contêineres Docker.

    Args:
        docker_compose: Comando do Docker Compose
    """
    # Iniciar build dos containers
    log_info("Iniciando build e download dos containers Docker...")
    run_command(f"{docker_compose} build")

    # Iniciar o banco de dados primeiro
    log_info("Iniciando o banco de dados...")
    run_command(f"{docker_compose} up -d db")
    log_info("Aguardando banco de dados inicializar...")
    time.sleep(10)  # Esperar o banco inicializar

    # Iniciar os gateways
    log_info("Iniciando gateways de pagamento...")
    run_command(f"{docker_compose} up -d gateway1 gateway2")
    log_info("Aguardando gateways inicializarem...")
    time.sleep(5)  # Esperar os gateways inicializarem

    # Iniciar o restante da aplicação
    log_info("Iniciando o restante da aplicação...")
    run_command(f"{docker_compose} up -d")


def check_app_key(docker_compose):
    """
    Verifica a chave da aplicação Laravel.

    Args:
        docker_compose: Comando do Docker Compose

    Returns:
        bool: True se a chave já existe, False caso contrário
    """
    log_info("Verificando chave da aplicação...")

    app_key_cmd = subprocess.run(
        f"{docker_compose} exec app php -r \"echo env('APP_KEY');\"",
        shell=True,
        capture_output=True,
        text=True,
        check=False  # Definido explicitamente
    )

    app_key = app_key_cmd.stdout.strip()

    if not app_key:
        log_info("Gerando nova chave da aplicação...")
        run_command(
            f"{docker_compose} exec app php artisan key:generate --force")
        log_success("Nova chave gerada com sucesso.")
        return False
    else:
        log_success("Chave da aplicação já existe. Mantendo a chave atual.")
        return True


def run_migrations(docker_compose, fresh_migrate):
    """
    Executa migrações no banco de dados.

    Args:
        docker_compose: Comando do Docker Compose
        fresh_migrate: Se deve executar migrações com --fresh
    """
    if fresh_migrate == "yes":
        log_info("Resetando banco de dados e executando migrações...")
        run_command(
            f"{docker_compose} exec app php artisan migrate:fresh --seed --force")
    else:
        log_info("Executando migrações sem resetar banco de dados...")
        try:
            migrate_cmd = subprocess.run(
                f"{docker_compose} exec app php artisan migrate --seed --force",
                shell=True,
                capture_output=True,
                text=True,
                check=False  # Definido explicitamente
            )
        except Exception:
            # Ignorar erros (tabelas já podem existir)
            pass


def optimize_laravel(docker_compose):
    """
    Otimiza a aplicação Laravel.

    Args:
        docker_compose: Comando do Docker Compose
    """
    log_info("Otimizando a aplicação...")

    commands = [
        "php artisan optimize",
        "php artisan view:clear",
        "php artisan cache:clear",
        "php artisan config:clear"
    ]

    for cmd in commands:
        run_command(f"{docker_compose} exec app {cmd}")


def check_laravel_accessibility():
    """
    Verifica se o Laravel está acessível via HTTP.

    Returns:
        bool: True se o Laravel está acessível, False caso contrário
    """
    log_info("Verificando se o Laravel está acessível...")

    try:
        curl_cmd = subprocess.run(
            "curl -s -o /dev/null -w \"%{http_code}\" \"http://localhost:8000\"",
            shell=True,
            capture_output=True,
            text=True,
            check=False  # Definido explicitamente
        )

        if curl_cmd.stdout.strip() == "200":
            log_success("Laravel está acessível via http://localhost:8000")
            return True
        else:
            log_warning("Não foi possível confirmar se o Laravel está acessível. "
                        "Tente acessar manualmente http://localhost:8000")
            return False
    except Exception:
        log_warning("Não foi possível verificar se o Laravel está acessível. "
                    "Tente acessar manualmente http://localhost:8000")
        return False


def show_summary(docker_compose):
    """
    Exibe um resumo do setup.

    Args:
        docker_compose: Comando do Docker Compose
    """
    print(f"\n{Colors.GREEN}SETUP CONCLUÍDO COM SUCESSO!{Colors.RESET}\n")

    print(f"{Colors.CYAN}=====================================")
    print("      INFORMAÇÕES DO SISTEMA")
    print(f"====================================={Colors.RESET}")
    print("Sua aplicação Laravel está rodando em:")
    print("- Aplicação Web: http://localhost:8000")
    print("- API: http://localhost:8000/api")
    print("- Acesso ao Banco: localhost:3306 (via cliente de banco de dados)")
    print("- Gateway 1: http://localhost:3001")
    print("- Gateway 2: http://localhost:3002")

    print(f"\n{Colors.YELLOW}=====================================")
    print("      USUÁRIOS DE TESTE")
    print(f"====================================={Colors.RESET}")
    print("Admin: admin@example.com / password")
    print("Finance: finance@example.com / password")
    print("Manager: manager@example.com / password")
    print("User: user@example.com / password")

    print(f"\n{Colors.YELLOW}=====================================")
    print("      COMANDOS ÚTEIS")
    print(f"====================================={Colors.RESET}")
    print("Para verificar o status dos contêineres:")
    print(f"  {docker_compose} ps")
    print("")
    print("Para ver os logs da aplicação:")
    print(f"  {docker_compose} logs -f app")
    print("")
    print("Para acessar o terminal do contêiner:")
    print(f"  {docker_compose} exec app bash")
    print("")
    print("Para executar os testes:")
    print("  python run-tests.py")
    print("")
    print("Para parar os contêineres:")
    print(f"  {docker_compose} down")
    print(f"{Colors.CYAN}====================================={Colors.RESET}\n")


def main():
    """Função principal do script."""
    # Banner de boas-vindas
    print(f"{Colors.CYAN}")
    print("=============================================")
    print("      Multi-Gateway Payment System Setup Tool")
    print(f"=============================================={Colors.RESET}")

    # Verificar requisitos do sistema
    log_info("Verificando requisitos do sistema...")

    docker_check = subprocess.run(
        "command -v docker",
        shell=True,
        capture_output=True,
        check=False  # Definido explicitamente
    )

    if docker_check.returncode != 0:
        log_error(
            "Docker não encontrado. Por favor, instale o Docker antes de continuar.")
        sys.exit(1)

    # Determinar comando do Docker Compose
    docker_compose = find_docker_compose()
    log_success(f"Usando comando: {docker_compose}")

    # Verificar diretório da aplicação Laravel
    setup_laravel_directory()

    # Configurar arquivos .env
    env_vars = setup_env_files()

    # Sincronizar variáveis de ambiente
    sync_env_variables(env_vars)

    # Criar configuração do Nginx
    create_nginx_config()

    # Verificar contêineres existentes
    check_existing_containers(docker_compose)

    # Obter opção de limpeza
    _, fresh_migrate = get_clean_option()

    # Construir e iniciar contêineres
    build_and_start_containers(docker_compose)

    # Verificar contêineres
    log_info("Verificando status dos contêineres...")
    run_command(f"{docker_compose} ps")

    # Verificar se a aplicação está pronta
    app_ready = check_app_readiness(docker_compose)

    if app_ready:
        # Instalar dependências do composer
        log_info("Instalando dependências do Composer...")
        run_command(
            f"{docker_compose} exec app composer install --no-interaction")

        # Verificar a chave da aplicação
        check_app_key(docker_compose)

        # Executar migrações
        run_migrations(docker_compose, fresh_migrate)

        # Otimizar o Laravel
        optimize_laravel(docker_compose)

        # Verificar se o Laravel está acessível
        check_laravel_accessibility()

        # Exibir resumo
        show_summary(docker_compose)
    else:
        log_error(
            "Não foi possível verificar se a aplicação está funcionando corretamente.")
        log_error("Verifique os logs com o comando:")
        print(f"  {docker_compose} logs app")
        sys.exit(1)


if __name__ == "__main__":
    main()
