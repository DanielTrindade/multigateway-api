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

# Banner
echo -e "\e[1;31m"
echo "====================================================="
echo "      Docker System Cleanup Tool"
echo "====================================================="
echo -e "\e[0m"

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

# Confirmar limpeza completa
echo -e "\e[1;31m⚠️  ATENÇÃO! ⚠️\e[0m"
echo "Esta ação irá:"
echo "1. Parar todos os contêineres em execução"
echo "2. Remover todos os contêineres, redes e volumes do projeto"
echo "3. Limpar imagens não utilizadas"
echo "4. Remover volumes Docker órfãos"
echo ""
echo -n "Tem certeza que deseja continuar? (S/n): "
read -r confirmation

if [[ "$confirmation" != "S" && "$confirmation" != "s" && "$confirmation" != "" ]]; then
    log_warning "Operação cancelada pelo usuário."
    exit 0
fi

# Parar todos os contêineres associados ao projeto
log_info "Parando todos os contêineres do projeto..."
$DOCKER_COMPOSE down --remove-orphans
log_success "Contêineres parados com sucesso."

# Remover todos os contêineres, redes e volumes deste projeto
log_info "Removendo todos os recursos do projeto..."
$DOCKER_COMPOSE down -v --remove-orphans
log_success "Recursos do projeto removidos."

# Limpar volumes Docker órfãos
log_info "Removendo volumes órfãos..."
docker volume ls -qf dangling=true | xargs -r docker volume rm
log_success "Volumes órfãos removidos."

# Opção para remover todas as imagens não utilizadas
echo -n "Deseja remover também todas as imagens não utilizadas? (s/N): "
read -r remove_images

if [[ "$remove_images" == "S" || "$remove_images" == "s" ]]; then
    log_info "Removendo imagens não utilizadas..."
    docker image prune -af
    log_success "Imagens não utilizadas removidas."
else
    log_info "Mantendo imagens não utilizadas."
fi

# Limpar arquivos de cache locais
echo -n "Deseja limpar os arquivos de cache do Laravel (bootstrap/cache, storage/framework)? (s/N): "
read -r clean_laravel

if [[ "$clean_laravel" == "S" || "$clean_laravel" == "s" ]]; then
    log_info "Limpando arquivos de cache do Laravel..."
    
    # Limpar diretórios de cache do Laravel
    if [ -d "multigateway-app/bootstrap/cache" ]; then
        rm -rf multigateway-app/bootstrap/cache/*.php
    fi
    
    if [ -d "multigateway-app/storage/framework/cache" ]; then
        rm -rf multigateway-app/storage/framework/cache/data/*
    fi
    
    if [ -d "multigateway-app/storage/framework/sessions" ]; then
        rm -rf multigateway-app/storage/framework/sessions/*
    fi
    
    if [ -d "multigateway-app/storage/framework/views" ]; then
        rm -rf multigateway-app/storage/framework/views/*
    fi
    
    log_success "Arquivos de cache do Laravel removidos."
fi

# Limpar dependências do Composer (vendor)
echo -n "Deseja remover o diretório vendor do Composer? (s/N): "
read -r clean_vendor

if [[ "$clean_vendor" == "S" || "$clean_vendor" == "s" ]]; then
    log_info "Removendo diretório vendor..."
    if [ -d "multigateway-app/vendor" ]; then
        rm -rf multigateway-app/vendor
    fi
    log_success "Diretório vendor removido."
fi

# Verificar e exibir espaço liberado
if command -v du &> /dev/null; then
    log_info "Espaço em disco atual:"
    df -h .
fi

echo -e "\n\e[1;42m LIMPEZA CONCLUÍDA COM SUCESSO! \e[0m\n"
echo -e "\e[1;36m====================================="
echo "      PRÓXIMOS PASSOS"
echo "=====================================\e[0m"
echo "Para reconstruir o ambiente, execute:"
echo "  ./setup.sh"
echo -e "\e[1;36m=====================================\e[0m\n"