#!/bin/bash

# Função para exibir mensagens formatadas
log_info() {
    echo -e "\e[34m[INFO]\e[0m $1"
}

log_success() {
    echo -e "\e[32m[SUCCESS]\e[0m $1"
}

log_warning() {
    echo -e "\e[33m[WARNING]\e[0m $1"
}

log_error() {
    echo -e "\e[31m[ERROR]\e[0m $1"
}

# Banner de boas-vindas
echo -e "\e[1;36m"
echo "====================================================="
echo "      Multi-Gateway Payment System Setup Tool"
echo "====================================================="
echo -e "\e[0m"

# Verificar Docker e Docker Compose
log_info "Verificando requisitos do sistema..."

if ! command -v docker &> /dev/null; then
    log_error "Docker não encontrado. Por favor, instale o Docker antes de continuar."
    exit 1
fi

# Determine se devemos usar docker-compose ou docker compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    log_error "Docker Compose não encontrado. Por favor, instale o Docker Compose antes de continuar."
    exit 1
fi

log_success "Usando comando: $DOCKER_COMPOSE"

# Verificar se o diretório da aplicação Laravel existe
if [ ! -d "multigateway-app" ]; then
    log_info "O diretório 'multigateway-app' não foi encontrado! Criando o diretório..."
    mkdir -p multigateway-app
    
    # Verificar se o Laravel está instalado no sistema
    if command -v composer &> /dev/null; then
        log_info "Criando um novo projeto Laravel na pasta multigateway-app..."
        composer create-project laravel/laravel multigateway-app
        log_success "Projeto Laravel criado com sucesso!"
    else
        log_warning "Composer não encontrado no sistema. O diretório multigateway-app foi criado, mas você precisará instalar o Laravel manualmente."
        log_info "Recomendação: instale o Composer e execute 'composer create-project laravel/laravel multigateway-app' na raiz do projeto."
    fi
fi

# Configurar arquivo .env na raiz
log_info "Configurando ambiente..."

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        log_info "Criando arquivo .env a partir do .env.example..."
        cp .env.example .env
        log_success "Arquivo .env criado com sucesso."
    else
        log_warning "Arquivo .env.example não encontrado na raiz do projeto. Criando .env básico..."
        cat > .env << EOL
DB_CONNECTION=mysql
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
EOL
        log_success "Arquivo .env básico criado com sucesso."
    fi
else
    log_info "Arquivo .env já existe na raiz do projeto."
fi

# Configurar o .env do Laravel
log_info "Configurando ambiente Laravel..."

if [ ! -f "multigateway-app/.env" ]; then
    if [ -f "multigateway-app/.env.example" ]; then
        log_info "Criando arquivo .env do Laravel a partir do .env.example..."
        cp multigateway-app/.env.example multigateway-app/.env
    elif [ -f ".env" ]; then
        log_info "Copiando .env da raiz para o diretório Laravel..."
        cp .env multigateway-app/.env
    else
        log_error "Não foi possível encontrar um arquivo .env.example para o Laravel."
        exit 1
    fi
    log_success "Arquivo .env do Laravel configurado."
else
    log_info "Arquivo .env do Laravel já existe."
fi

# Função para atualizar uma linha no .env
update_env_line() {
    local key=$1
    local value=$2
    local env_file=$3

    # Verifica se a linha já existe
    if grep -q "^$key=" "$env_file"; then
        # Substitui a linha existente
        sed -i "s|^$key=.*|$key=$value|" "$env_file"
    else
        # Adiciona a linha se não existir
        echo "$key=$value" >> "$env_file"
    fi
    
    log_info "Configurado $key=$value em $env_file"
}

# Carregar variáveis do .env raiz
if [ -f ".env" ]; then
    source .env
fi

# Atualizar variáveis nos arquivos .env
log_info "Sincronizando variáveis de ambiente..."

# Database
update_env_line "DB_CONNECTION" "${DB_CONNECTION:-mysql}" "multigateway-app/.env"
update_env_line "DB_HOST" "${DB_HOST:-db}" "multigateway-app/.env"
update_env_line "DB_PORT" "${DB_PORT:-3306}" "multigateway-app/.env"
update_env_line "DB_DATABASE" "${DB_DATABASE:-multigateway-db}" "multigateway-app/.env"
update_env_line "DB_USERNAME" "${DB_USERNAME:-multigateway}" "multigateway-app/.env"
update_env_line "DB_PASSWORD" "${DB_PASSWORD:-multigateway_password}" "multigateway-app/.env"

# Gateway 1
update_env_line "GATEWAY1_URL" "${GATEWAY1_URL:-http://gateway1:3001}" "multigateway-app/.env"
update_env_line "GATEWAY1_EMAIL" "${GATEWAY1_EMAIL:-dev@betalent.tech}" "multigateway-app/.env"
update_env_line "GATEWAY1_TOKEN" "${GATEWAY1_TOKEN:-FEC9BB078BF338F464F96B48089EB498}" "multigateway-app/.env"

# Gateway 2
update_env_line "GATEWAY2_URL" "${GATEWAY2_URL:-http://gateway2:3002}" "multigateway-app/.env"
update_env_line "GATEWAY2_AUTH_TOKEN" "${GATEWAY2_AUTH_TOKEN:-tk_f2198cc671b5289fa856}" "multigateway-app/.env"
update_env_line "GATEWAY2_AUTH_SECRET" "${GATEWAY2_AUTH_SECRET:-3d15e8ed6131446ea7e3456728b1211f}" "multigateway-app/.env"

# Create necessary directories
log_info "Criando diretórios necessários..."
mkdir -p docker/nginx/conf.d

# Create or update nginx config
if [ ! -f "docker/nginx/conf.d/app.conf" ]; then
    log_info "Criando configuração do Nginx..."
    cat > docker/nginx/conf.d/app.conf << EOL
server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html/public;
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
        gzip_static on;
    }
}
EOL
    log_success "Configuração do Nginx criada com sucesso."
fi

# Verifica se já há contêineres rodando e os para se necessário
log_info "Verificando contêineres existentes..."
if $DOCKER_COMPOSE ps -q | grep -q "."; then
    log_warning "Contêineres existentes encontrados. Parando-os antes de continuar..."
    $DOCKER_COMPOSE down
    log_success "Contêineres parados com sucesso."
fi

# Opções de limpeza para o ambiente
echo -e "\e[1;33m====================================="
echo "      OPÇÕES DE LIMPEZA"
echo "=====================================\e[0m"
echo "Escolha uma opção:"
echo "1. Manter todos os dados (recomendado para continuar desenvolvimento)"
echo "2. Limpar apenas dados do banco de dados (mantém volumes Docker)"
echo "3. Limpar todos os volumes Docker (ambiente totalmente novo)"
echo -n "Digite o número da opção (1-3) [1]: "
read -r clean_option

# Valor padrão se nada for digitado
clean_option=${clean_option:-1}

case $clean_option in
    1)
        log_info "Mantendo todos os dados existentes."
        FRESH_MIGRATE="no"
        ;;
    2)
        log_info "Limpando apenas dados do banco de dados."
        FRESH_MIGRATE="yes"
        ;;
    3)
        log_warning "Limpando todos os volumes Docker..."
        docker volume prune -f
        log_success "Volumes limpos com sucesso."
        FRESH_MIGRATE="yes"
        ;;
    *)
        log_warning "Opção inválida. Usando opção padrão (1) - Manter todos os dados."
        FRESH_MIGRATE="no"
        ;;
esac

# Inicia o build dos containers
log_info "Iniciando build e download dos containers Docker..."
$DOCKER_COMPOSE build

# Inicia o banco de dados primeiro para que esteja pronto quando a aplicação iniciar
log_info "Iniciando o banco de dados..."
$DOCKER_COMPOSE up -d db
log_info "Aguardando banco de dados inicializar..."
sleep 10  # Espera o banco inicializar

# Inicia os gateways
log_info "Iniciando gateways de pagamento..."
$DOCKER_COMPOSE up -d gateway1 gateway2
log_info "Aguardando gateways inicializarem..."
sleep 5  # Espera os gateways inicializarem

# Inicia o restante da aplicação
log_info "Iniciando o restante da aplicação..."
$DOCKER_COMPOSE up -d

# Verificar se os contêineres estão funcionando
log_info "Verificando status dos contêineres..."

# Lista os contêineres iniciados
$DOCKER_COMPOSE ps

# Verificar se o contêiner da aplicação está pronto
APP_READY=false
MAX_ATTEMPTS=15
ATTEMPT=1

while [ $ATTEMPT -le $MAX_ATTEMPTS ]; do
    log_info "Verificando aplicação Laravel... (Tentativa $ATTEMPT de $MAX_ATTEMPTS)"
    
    if $DOCKER_COMPOSE exec app php -v &> /dev/null; then
        log_success "Aplicação está rodando!"
        APP_READY=true
        break
    else
        if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
            log_error "Tempo limite excedido. A aplicação pode não estar funcionando corretamente."
        else
            log_warning "Aplicação ainda não está pronta. Aguardando..."
            sleep 8
            ATTEMPT=$((ATTEMPT+1))
        fi
    fi
done

if [ "$APP_READY" = true ]; then
    # Instalar dependências do composer
    log_info "Instalando dependências do Composer..."
    $DOCKER_COMPOSE exec app composer install --no-interaction

    log_info "Verificando chave da aplicação..."
    APP_KEY=$($DOCKER_COMPOSE exec app php -r "echo env('APP_KEY');")
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" == "" ]; then
        log_info "Gerando nova chave da aplicação..."
        $DOCKER_COMPOSE exec app php artisan key:generate --force
        log_success "Nova chave gerada com sucesso."
    else
        log_success "Chave da aplicação já existe. Mantendo a chave atual."
    fi


    # Executar migrações, com opção de limpar o banco ou não
    if [ "$FRESH_MIGRATE" = "yes" ]; then
        log_info "Resetando banco de dados e executando migrações..."
        $DOCKER_COMPOSE exec app php artisan migrate:fresh --seed --force
    else
        log_info "Executando migrações sem resetar banco de dados..."
        # Tenta executar migrações comuns, ignorando erros (tabelas já existem)
        $DOCKER_COMPOSE exec app php artisan migrate --seed --force || true
    fi

    # Otimizar o Laravel
    log_info "Otimizando a aplicação..."
    $DOCKER_COMPOSE exec app php artisan optimize
    $DOCKER_COMPOSE exec app php artisan view:clear
    $DOCKER_COMPOSE exec app php artisan cache:clear
    $DOCKER_COMPOSE exec app php artisan config:clear
    

    # Verificar se o Laravel está acessível
    log_info "Verificando se o Laravel está acessível..."
    if curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000" | grep -q "200"; then
        log_success "Laravel está acessível via http://localhost:8000"
    else
        log_warning "Não foi possível confirmar se o Laravel está acessível. Tente acessar manualmente http://localhost:8000"
    fi
else
    log_error "Não foi possível verificar se a aplicação está funcionando corretamente. Verifique os logs para mais detalhes:"
    $DOCKER_COMPOSE logs app
    exit 1
fi

# Exibir resumo
echo -e "\n\e[1;42m SETUP CONCLUÍDO COM SUCESSO! \e[0m\n"
echo -e "\e[1;36m====================================="
echo "      INFORMAÇÕES DO SISTEMA"
echo "=====================================\e[0m"
echo "Sua aplicação Laravel está rodando em:"
echo "- Aplicação Web: http://localhost:8000"
echo "- API: http://localhost:8000/api"
echo "- Acesso ao Banco: localhost:3306 (via cliente de banco de dados)"
echo "- Gateway 1: http://localhost:3001"
echo "- Gateway 2: http://localhost:3002"
echo -e "\n\e[1;33m====================================="
echo "      USUÁRIOS DE TESTE"
echo "=====================================\e[0m"
echo "Admin: admin@example.com / password"
echo "Finance: finance@example.com / password"
echo "Manager: manager@example.com / password"
echo "User: user@example.com / password"
echo -e "\n\e[1;33m====================================="
echo "      COMANDOS ÚTEIS"
echo "=====================================\e[0m"
echo "Para verificar o status dos contêineres:"
echo "  $DOCKER_COMPOSE ps"
echo ""
echo "Para ver os logs da aplicação:"
echo "  $DOCKER_COMPOSE logs -f app"
echo ""
echo "Para acessar o terminal do contêiner:"
echo "  $DOCKER_COMPOSE exec app bash"
echo ""
echo "Para executar os testes:"
echo "  ./run-tests.sh"
echo ""
echo "Para parar os contêineres:"
echo "  $DOCKER_COMPOSE down"
echo -e "\e[1;36m=====================================\e[0m\n"