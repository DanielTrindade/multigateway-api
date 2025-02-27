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
    log_error "O diretório 'multigateway-app' não foi encontrado!"
    log_error "Certifique-se de que seu projeto Laravel está no diretório 'multigateway-app'."
    exit 1
fi

# Configurar arquivo .env na raiz
log_info "Configurando ambiente..."

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        log_info "Criando arquivo .env a partir do .env.example..."
        cp .env.example .env
        log_success "Arquivo .env criado com sucesso."
    else
        log_error "Arquivo .env.example não encontrado na raiz do projeto."
        exit 1
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

# Build and start the Docker containers
log_info "Construindo e iniciando os contêineres Docker..."
$DOCKER_COMPOSE down

# Verifica se a pasta vendor existe na aplicação Laravel
if [ ! -d "multigateway-app/vendor" ]; then
    log_warning "Pasta vendor não encontrada. Verificando se o composer.json existe..."
    
    if [ -f "multigateway-app/composer.json" ]; then
        log_info "Instalando dependências localmente para garantir compatibilidade..."
        
        # Verificar se o Composer está instalado
        if command -v composer &> /dev/null; then
            (cd multigateway-app && composer install --no-interaction)
            log_success "Dependências instaladas localmente com sucesso!"
        else
            log_warning "Composer não encontrado localmente. Continuando com a instalação no container..."
        fi
    fi
fi

# Inicia o build dos containers
log_info "Iniciando build dos containers Docker..."
$DOCKER_COMPOSE build --no-cache

# Inicia os containers
log_info "Iniciando containers Docker..."
$DOCKER_COMPOSE up -d

# Verificar se os contêineres estão funcionando
log_info "Aguardando os contêineres iniciarem..."
attempt=1
max_attempts=10  # Aumentado para dar mais tempo
while [ $attempt -le $max_attempts ]; do
    log_info "Verificando contêineres... Tentativa $attempt de $max_attempts"
    
    if $DOCKER_COMPOSE ps | grep -q "multigateway-app.*Up"; then
        log_success "Contêiner da aplicação está rodando!"
        
        # Verificar vendor
        if ! $DOCKER_COMPOSE exec app ls -la vendor &> /dev/null; then
            log_warning "Pasta vendor não encontrada no container. Instalando dependências..."
            $DOCKER_COMPOSE exec app composer install --no-interaction
        fi
        
        break
    else
        if [ $attempt -eq $max_attempts ]; then
            log_error "Tempo limite excedido. Verifique os logs com '$DOCKER_COMPOSE logs app'"
            exit 1
        fi
        log_warning "Contêiner ainda não está pronto. Aguardando..."
        sleep 15  # Aumentado para dar mais tempo
        attempt=$((attempt+1))
    fi
done

# Aplicar configurações do Laravel
log_info "Configurando a aplicação Laravel..."

# Verificar se o vendor/autoload.php existe
log_info "Verificando dependências do Laravel..."
if ! $DOCKER_COMPOSE exec app test -f /var/www/html/vendor/autoload.php; then
    log_warning "O arquivo vendor/autoload.php não foi encontrado!"
    log_info "Tentando instalar dependências no container..."
    
    # Tentar instalar dependências novamente
    $DOCKER_COMPOSE exec app composer install --no-interaction
    
    # Verificar novamente
    if ! $DOCKER_COMPOSE exec app test -f /var/www/html/vendor/autoload.php; then
        log_error "Falha ao instalar dependências do Laravel!"
        log_error "Conteúdo do diretório do aplicativo:"
        $DOCKER_COMPOSE exec app ls -la
        log_error "Verificando a existência de composer.json:"
        $DOCKER_COMPOSE exec app cat composer.json || echo "Arquivo composer.json não encontrado!"
        exit 1
    fi
fi

# Verificar se o Laravel está funcionando corretamente
MAX_RETRY=3
RETRY=0
while [ $RETRY -lt $MAX_RETRY ]; do
    if $DOCKER_COMPOSE exec app php artisan --version &> /dev/null; then
        log_success "Laravel está funcionando corretamente!"
        
        log_info "Gerando chave da aplicação..."
        $DOCKER_COMPOSE exec app php artisan key:generate --force
        
        log_info "Executando migrações e seeders..."
        $DOCKER_COMPOSE exec app php artisan migrate --seed
        log_success "Migrações executadas com sucesso!"
        break
    else
        RETRY=$((RETRY+1))
        if [ $RETRY -eq $MAX_RETRY ]; then
            log_error "Laravel não está funcionando corretamente após $MAX_RETRY tentativas!"
            log_error "Verificando permissões e estrutura de diretórios:"
            $DOCKER_COMPOSE exec app ls -la
            log_error "Logs do contêiner:"
            $DOCKER_COMPOSE logs app
            exit 1
        fi
        log_warning "Tentativa $RETRY de $MAX_RETRY falhou. Aguardando e tentando novamente..."
        sleep 5
    fi
done

log_info "Limpando cache e otimizando..."
$DOCKER_COMPOSE exec app php artisan optimize
$DOCKER_COMPOSE exec app php artisan view:clear
$DOCKER_COMPOSE exec app php artisan cache:clear
log_success "Aplicação otimizada!"

# Exibir resumo
echo -e "\n\e[1;42m SETUP CONCLUÍDO COM SUCESSO! \e[0m\n"
echo -e "\e[1;36m====================================="
echo "      INFORMAÇÕES DO SISTEMA"
echo "=====================================\e[0m"
echo "Sua aplicação Laravel está rodando em:"
echo "- Aplicação Web: http://localhost:8000"
echo "- API: http://localhost:8000/api"
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
echo "Para parar os contêineres:"
echo "  $DOCKER_COMPOSE down"
echo -e "\e[1;36m=====================================\e[0m\n"