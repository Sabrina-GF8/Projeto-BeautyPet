import AsyncStorage from '@react-native-async-storage/async-storage';
import { supabase } from './supabase';

// ============================================================
// BEAUTY PET - CAMADA ÚNICA DE DADOS DO MOBILE
// React Native -> Supabase Auth/PostgreSQL
// ============================================================

export type ClienteSessao = {
  id: number;
  nome: string;
  email?: string | null;
};

type Registro = Record<string, any>;

const CHAVE_TOKEN = '@beautypet:token';
const CHAVE_CLIENTE = '@beautypet:cliente';

let tokenSessao: string | null = null;
let clienteSessao: ClienteSessao | null = null;
let sessaoJaCarregada = false;

// HEURÍSTICA 9 (reconhecer, diagnosticar e recuperar erros):
// antes a mensagem técnica do Supabase (em inglês) ia direto para o Alert.
// Agora os erros conhecidos viram frases em português que dizem o que fazer,
// e o detalhe técnico fica só no console para o desenvolvedor.
export function mensagemErro(erro: any, padrao = 'Não foi possível concluir a operação.') {
  const tecnico = String(erro?.message || erro?.error_description || '');
  const codigo = String(erro?.code || '');
  if (tecnico) console.warn('[BeautyPet] erro técnico:', codigo, tecnico);

  const texto = tecnico.toLowerCase();

  if (texto.includes('network request failed') || texto.includes('failed to fetch') || texto.includes('network')) {
    return 'Sem conexão com a internet. Verifique o Wi-Fi ou os dados móveis e tente novamente.';
  }
  if (codigo === '23505' || texto.includes('duplicate key')) {
    return 'Esse registro já existe. Confira se ele não foi cadastrado antes.';
  }
  if (codigo === '23503' || texto.includes('foreign key')) {
    return 'Um dos itens selecionados não existe mais. Atualize a tela e selecione novamente.';
  }
  if (codigo === '22P02' || texto.includes('invalid input syntax')) {
    return 'Algum campo está em formato inválido. Confira datas, valores e números digitados.';
  }
  if (codigo === 'PGRST301' || texto.includes('jwt') || texto.includes('expired')) {
    return 'Sua sessão expirou. Saia e entre novamente no aplicativo.';
  }
  if (codigo === '42501' || texto.includes('row-level security') || texto.includes('permission denied')) {
    return 'Você não tem permissão para esta ação. Entre novamente na sua conta.';
  }
  if (texto.includes('user already registered')) {
    return 'Este e-mail já tem uma conta. Volte e toque em Entrar.';
  }
  if (texto.includes('password should be at least')) {
    return 'A senha precisa ter pelo menos 6 caracteres.';
  }
  if (texto.includes('email not confirmed')) {
    return 'Confirme seu e-mail pelo link enviado antes de entrar.';
  }
  if (texto.includes('rate limit') || texto.includes('too many')) {
    return 'Muitas tentativas seguidas. Aguarde alguns minutos e tente novamente.';
  }

  return padrao;
}

// HEURÍSTICA 9: formato de e-mail realmente válido (antes bastava ter "@").
function emailValido(email: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}

// HEURÍSTICA 9: aceita "12,50" (vírgula) — antes virava NaN e passava pela validação.
function numeroDigitado(valor: unknown) {
  let texto = String(valor ?? '').trim();
  // "1.234,50" -> "1234.50"; "12.5" continua "12.5".
  if (texto.includes(',')) texto = texto.replace(/\./g, '').replace(',', '.');
  if (texto === '') return NaN;
  return Number(texto);
}

function hojeIso() {
  const agora = new Date();
  return `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, '0')}-${String(agora.getDate()).padStart(2, '0')}`;
}

function normalizarTexto(valor: unknown) {
  return String(valor ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('pt-BR')
    .trim();
}

function inicioFimMes(ano: number, mes: number) {
  const inicio = `${ano}-${String(mes).padStart(2, '0')}-01`;
  const proximo = new Date(ano, mes, 1);
  const fim = `${proximo.getFullYear()}-${String(proximo.getMonth() + 1).padStart(2, '0')}-01`;
  return { inicio, fim };
}

function dataHoraLocal(data: string, horario: string) {
  return `${data}T${horario}:00`;
}

function somarMinutosLocal(data: string, horario: string, minutos: number) {
  const [ano, mes, dia] = data.split('-').map(Number);
  const [hora, minuto] = horario.split(':').map(Number);
  const valor = new Date(ano, mes - 1, dia, hora, minuto + minutos, 0, 0);
  const y = valor.getFullYear();
  const m = String(valor.getMonth() + 1).padStart(2, '0');
  const d = String(valor.getDate()).padStart(2, '0');
  const h = String(valor.getHours()).padStart(2, '0');
  const min = String(valor.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${d}T${h}:${min}:00`;
}

function minutosDoHorario(valor?: string | null) {
  if (!valor) return 0;
  const parteHora = valor.includes('T') ? valor.split('T')[1] : valor.split(' ')[1];
  const [hora, minuto] = String(parteHora || '00:00').slice(0, 5).split(':').map(Number);
  return (hora || 0) * 60 + (minuto || 0);
}

async function carregarSessaoSalva() {
  if (sessaoJaCarregada) return;
  try {
    const [tokenSalvo, clienteSalvo] = await Promise.all([
      AsyncStorage.getItem(CHAVE_TOKEN),
      AsyncStorage.getItem(CHAVE_CLIENTE),
    ]);
    tokenSessao = tokenSalvo;
    clienteSessao = clienteSalvo ? JSON.parse(clienteSalvo) : null;
  } finally {
    sessaoJaCarregada = true;
  }
}

export async function definirSessao(token: string, cliente: ClienteSessao) {
  tokenSessao = token;
  clienteSessao = cliente;
  sessaoJaCarregada = true;
  await Promise.all([
    AsyncStorage.setItem(CHAVE_TOKEN, token),
    AsyncStorage.setItem(CHAVE_CLIENTE, JSON.stringify(cliente)),
  ]);
}

export async function limparSessao() {
  tokenSessao = null;
  clienteSessao = null;
  sessaoJaCarregada = true;
  await Promise.all([
    AsyncStorage.removeItem(CHAVE_TOKEN),
    AsyncStorage.removeItem(CHAVE_CLIENTE),
  ]);
}

export function obterClienteSessao() {
  return clienteSessao;
}

export function obterTokenSessao() {
  return tokenSessao;
}

async function usuarioAtual() {
  const { data, error } = await supabase.auth.getUser();
  if (error || !data.user) {
    throw new Error('Sessão inválida ou expirada. Entre novamente.');
  }
  return data.user;
}

async function clienteAtual() {
  await carregarSessaoSalva();
  const usuario = await usuarioAtual();

  const { data, error } = await supabase
    .from('clientes')
    .select('id,nome,email')
    .eq('auth_user_id', usuario.id)
    .eq('ativo', true)
    .maybeSingle();

  if (error) throw new Error(mensagemErro(error, 'Não foi possível localizar a ficha do tutor.'));

  if (!data) {
    const nome = String(usuario.user_metadata?.nome || usuario.email?.split('@')[0] || 'Tutor');
    const { data: criado, error: erroCriacao } = await supabase
      .from('clientes')
      .insert({
        auth_user_id: usuario.id,
        nome,
        email: usuario.email || null,
        telefone_principal: '',
        ativo: true,
      })
      .select('id,nome,email')
      .single();

    if (erroCriacao || !criado) {
      throw new Error(mensagemErro(erroCriacao, 'Sua conta existe, mas não foi possível criar a ficha do tutor.'));
    }

    clienteSessao = { id: Number(criado.id), nome: criado.nome, email: criado.email };
    await AsyncStorage.setItem(CHAVE_CLIENTE, JSON.stringify(clienteSessao));
    return clienteSessao;
  }

  clienteSessao = { id: Number(data.id), nome: data.nome, email: data.email };
  await AsyncStorage.setItem(CHAVE_CLIENTE, JSON.stringify(clienteSessao));
  return clienteSessao;
}

async function petsDoCliente(clienteId: number) {
  const { data, error } = await supabase
    .from('pets')
    .select('*')
    .eq('cliente_id', clienteId)
    .eq('ativo', true)
    .order('nome');
  if (error) throw new Error(mensagemErro(error, 'Não foi possível carregar os pets.'));
  return data || [];
}

async function nomesServicosPorAgendamento(agendamentoIds: number[]) {
  const resultado = new Map<number, string[]>();
  if (agendamentoIds.length === 0) return resultado;

  const { data: itens, error: erroItens } = await supabase
    .from('agendamento_itens')
    .select('agendamento_id,servico_id')
    .in('agendamento_id', agendamentoIds);
  if (erroItens) throw new Error(mensagemErro(erroItens));

  const servicoIds = [...new Set((itens || []).map((item: any) => Number(item.servico_id)))];
  const nomes = new Map<number, string>();

  if (servicoIds.length > 0) {
    const { data: servicos, error: erroServicos } = await supabase
      .from('servicos')
      .select('id,nome')
      .in('id', servicoIds);
    if (erroServicos) throw new Error(mensagemErro(erroServicos));
    (servicos || []).forEach((servico: any) => nomes.set(Number(servico.id), servico.nome));
  }

  (itens || []).forEach((item: any) => {
    const id = Number(item.agendamento_id);
    const lista = resultado.get(id) || [];
    const nome = nomes.get(Number(item.servico_id));
    if (nome && !lista.includes(nome)) lista.push(nome);
    resultado.set(id, lista);
  });

  return resultado;
}

async function dadosServicos(ids?: number[]) {
  let consulta = supabase
    .from('servicos')
    .select('id,nome,descricao,categoria_id,ativo')
    .eq('ativo', true)
    .order('nome');

  if (ids?.length) consulta = consulta.in('id', ids);

  const { data: servicos, error } = await consulta;
  if (error) throw new Error(mensagemErro(error, 'Não foi possível carregar os serviços.'));

  const categoriaIds = [...new Set((servicos || []).map((item: any) => Number(item.categoria_id)))];
  const categorias = new Map<number, string>();

  if (categoriaIds.length > 0) {
    const { data, error: erroCategorias } = await supabase
      .from('categorias_servico')
      .select('id,nome')
      .in('id', categoriaIds);
    if (erroCategorias) throw new Error(mensagemErro(erroCategorias));
    (data || []).forEach((item: any) => categorias.set(Number(item.id), item.nome));
  }

  return (servicos || []).map((servico: any) => ({
    ...servico,
    id: Number(servico.id),
    categoria: categorias.get(Number(servico.categoria_id)) || '',
  }));
}

async function horariosDisponiveis(data: string, servicoIds: number[], duracaoMinutos = 60) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(data) || servicoIds.length === 0) return [];

  // O cálculo de vagas roda dentro do PostgreSQL como SECURITY DEFINER.
  // Assim o tutor recebe somente os horários livres e nunca enxerga
  // os agendamentos de outros clientes usados no cálculo da ocupação.
  const { data: horarios, error } = await supabase.rpc('fn_mobile_horarios_disponiveis', {
    p_data: data,
    p_servico_ids: servicoIds,
    p_duracao_minutos: duracaoMinutos,
  });

  if (error) {
    throw new Error(mensagemErro(error, 'Não foi possível consultar os horários disponíveis.'));
  }

  return (horarios || []).map((item: any) =>
    typeof item === 'string' ? item.slice(0, 5) : String(item.horario || '').slice(0, 5)
  );
}

async function sincronizarNotificacoes(clienteId: number) {
  const pets = await petsDoCliente(clienteId);
  const petMap = new Map(pets.map((pet: any) => [Number(pet.id), pet.nome]));
  const petIds = pets.map((pet: any) => Number(pet.id));
  if (!petIds.length) return;

  const { data: agendamentos, error } = await supabase
    .from('agendamentos')
    .select('id,pet_id,status,data_hora_inicio,origem')
    .in('pet_id', petIds)
    .eq('origem', 'MOBILE')
    .in('status', ['CONFIRMADO', 'RECUSADO', 'CANCELADO']);
  if (error) return;

  const nomes = await nomesServicosPorAgendamento((agendamentos || []).map((item: any) => Number(item.id)));
  const { data: existentes } = await supabase
    .from('notificacoes_mobile')
    .select('agendamento_id,titulo')
    .eq('cliente_id', clienteId);
  const chaves = new Set((existentes || []).map((item: any) => `${item.agendamento_id}|${item.titulo}`));

  for (const item of agendamentos || []) {
    const titulo = item.status === 'CONFIRMADO'
      ? 'Agendamento confirmado'
      : item.status === 'RECUSADO'
        ? 'Agendamento recusado'
        : 'Agendamento cancelado';
    const chave = `${item.id}|${titulo}`;
    if (chaves.has(chave)) continue;

    const dataObj = new Date(String(item.data_hora_inicio).replace(' ', 'T'));
    const dataTexto = dataObj.toLocaleDateString('pt-BR');
    const horaTexto = dataObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const servicos = (nomes.get(Number(item.id)) || ['Serviço']).join(', ');
    const pet = petMap.get(Number(item.pet_id)) || 'pet';

    await supabase.from('notificacoes_mobile').insert({
      cliente_id: clienteId,
      pet_id: item.pet_id,
      agendamento_id: item.id,
      titulo,
      mensagem: `${titulo} para ${pet}: ${servicos} em ${dataTexto} às ${horaTexto}.`,
      tipo: 'AGENDAMENTO',
    });
  }
}

export async function api<T = any>(acao: string, dados: Record<string, unknown> = {}): Promise<T> {
  await carregarSessaoSalva();

  if (acao === 'teste_conexao') {
    const { error } = await supabase.from('especies').select('id').limit(1);
    if (error) throw new Error(mensagemErro(error, 'Não foi possível conectar ao Supabase.'));
    return { sucesso: true, mensagem: 'Supabase conectado com sucesso.' } as T;
  }

  if (acao === 'cadastro') {
    const nome = String(dados.nome || '').trim();
    const email = String(dados.email || '').trim().toLocaleLowerCase('pt-BR');
    const senha = String(dados.senha || '');
    // HEURÍSTICA 9: uma mensagem por problema, dizendo exatamente o que corrigir.
    if (!nome) throw new Error('Informe seu nome.');
    if (!emailValido(email)) throw new Error('Informe um e-mail válido (ex.: nome@email.com).');
    if (senha.length < 6) throw new Error('A senha precisa ter pelo menos 6 caracteres.');
    const { data, error } = await supabase.auth.signUp({
      email,
      password: senha,
      options: { data: { nome } },
    });
    if (error) throw new Error(mensagemErro(error, 'Não foi possível criar a conta.'));
    const mensagem = data.session
      ? 'Cadastro realizado com sucesso.'
      : 'Cadastro realizado. Se a confirmação de e-mail estiver ativa no Supabase, confirme o e-mail antes de entrar.';
    if (data.session) await supabase.auth.signOut();
    return { sucesso: true, mensagem } as T;
  }

  if (acao === 'login') {
    const email = String(dados.email || '').trim().toLocaleLowerCase('pt-BR');
    const senha = String(dados.senha || '');
    if (!email || !senha) throw new Error('Digite o e-mail e a senha.');
    const { data, error } = await supabase.auth.signInWithPassword({ email, password: senha });
    // HEURÍSTICA 9: diferencia falta de internet / e-mail não confirmado de senha errada.
    if (error) {
      const texto = String(error.message || '').toLowerCase();
      if (texto.includes('network') || texto.includes('fetch') || texto.includes('email not confirmed')) {
        throw new Error(mensagemErro(error));
      }
      throw new Error('E-mail ou senha incorretos. Confira e tente de novo.');
    }
    if (!data.session) throw new Error('E-mail ou senha incorretos. Confira e tente de novo.');
    const cliente = await clienteAtual();
    return {
      sucesso: true,
      token: data.session.access_token,
      cliente,
    } as T;
  }

  if (acao === 'logout') {
    await supabase.auth.signOut();
    await limparSessao();
    return { sucesso: true } as T;
  }

  const cliente = await clienteAtual();
  const clienteId = cliente.id;

  if (acao === 'home') {
    await sincronizarNotificacoes(clienteId);
    const pets = await petsDoCliente(clienteId);
    const petMap = new Map(pets.map((pet: any) => [Number(pet.id), pet.nome]));
    const petIds = pets.map((pet: any) => Number(pet.id));
    let proximo: any = null;

    if (petIds.length) {
      const agora = new Date().toISOString();
      const { data: agendamentos, error } = await supabase
        .from('agendamentos')
        .select('id,pet_id,data_hora_inicio,status')
        .in('pet_id', petIds)
        .gte('data_hora_inicio', agora)
        .in('status', ['SOLICITADO', 'CONFIRMADO'])
        .order('data_hora_inicio')
        .limit(1);
      if (error) throw new Error(mensagemErro(error));
      if (agendamentos?.[0]) {
        const item = agendamentos[0];
        const nomes = await nomesServicosPorAgendamento([Number(item.id)]);
        proximo = {
          ...item,
          pet: petMap.get(Number(item.pet_id)) || '',
          servicos: (nomes.get(Number(item.id)) || []).join(', '),
        };
      }
    }

    const { count } = await supabase
      .from('notificacoes_mobile')
      .select('id', { count: 'exact', head: true })
      .eq('cliente_id', clienteId)
      .eq('lida', false);

    return {
      sucesso: true,
      cliente: { id: clienteId, nome: cliente.nome },
      proximo_agendamento: proximo,
      notificacoes_nao_lidas: Number(count || 0),
    } as T;
  }

  if (acao === 'perfil' || acao === 'meus_pets') {
    const { data: ficha, error: erroFicha } = await supabase
      .from('clientes')
      .select('id,nome,cpf,email,telefone_principal,telefone_reserva,cep,logradouro,numero,complemento,bairro,cidade,estado,referencia,observacoes')
      .eq('id', clienteId)
      .single();
    if (erroFicha) throw new Error(mensagemErro(erroFicha));

    const pets = await petsDoCliente(clienteId);
    const especieIds = [...new Set(pets.map((pet: any) => Number(pet.especie_id)).filter(Boolean))];
    const racaIds = [...new Set(pets.map((pet: any) => Number(pet.raca_id)).filter(Boolean))];
    const especies = new Map<number, string>();
    const racas = new Map<number, string>();

    if (especieIds.length) {
      const { data } = await supabase.from('especies').select('id,nome').in('id', especieIds);
      (data || []).forEach((item: any) => especies.set(Number(item.id), item.nome));
    }
    if (racaIds.length) {
      const { data } = await supabase.from('racas').select('id,nome').in('id', racaIds);
      (data || []).forEach((item: any) => racas.set(Number(item.id), item.nome));
    }

    return {
      sucesso: true,
      cliente: ficha,
      pets: pets.map((pet: any) => ({
        ...pet,
        id: Number(pet.id),
        especie: especies.get(Number(pet.especie_id)) || '',
        raca: racas.get(Number(pet.raca_id)) || null,
      })),
    } as T;
  }

  if (acao === 'catalogo_pet') {
    const [{ data: especies, error: erroEspecies }, { data: racas, error: erroRacas }] = await Promise.all([
      supabase.from('especies').select('id,nome').eq('ativo', true).order('nome'),
      supabase.from('racas').select('id,especie_id,nome').eq('ativo', true).order('nome'),
    ]);
    if (erroEspecies || erroRacas) throw new Error(mensagemErro(erroEspecies || erroRacas));
    return { sucesso: true, especies: especies || [], racas: racas || [] } as T;
  }

  if (acao === 'adicionar_pet') {
    const nome = String(dados.nome || '').trim();
    const especieId = Number(dados.especie_id || 0);
    if (!nome) throw new Error('Informe o nome do pet.');
    if (!especieId) throw new Error('Selecione a espécie do pet.');
    // HEURÍSTICA 9: data de nascimento no futuro é impossível.
    if (dados.data_nascimento && String(dados.data_nascimento) > hojeIso()) {
      throw new Error('A data de nascimento não pode ser depois de hoje.');
    }
    const { data, error } = await supabase.from('pets').insert({
      cliente_id: clienteId,
      nome,
      especie_id: especieId,
      raca_id: dados.raca_id ? Number(dados.raca_id) : null,
      sexo: dados.sexo || 'NAO_INFORMADO',
      cor: String(dados.cor || '').trim() || null,
      data_nascimento: dados.data_nascimento || null,
      nascimento_aproximado: false,
      castrado: Boolean(dados.castrado),
      ativo: true,
    }).select('id').single();
    if (error) throw new Error(mensagemErro(error, 'Não foi possível cadastrar o pet.'));
    return { sucesso: true, mensagem: 'Pet cadastrado e vinculado à ficha do tutor.', pet_id: Number(data.id) } as T;
  }

  if (acao === 'servicos') {
    const servicos = await dadosServicos();
    return { sucesso: true, servicos } as T;
  }

  if (acao === 'horarios_disponiveis') {
    const data = String(dados.data || '');
    const ids = Array.isArray(dados.servico_ids) ? dados.servico_ids.map(Number).filter(Boolean) : [];
    const duracao = Number(dados.duracao_minutos || 60);
    return { sucesso: true, horarios: await horariosDisponiveis(data, ids, duracao) } as T;
  }

  if (acao === 'solicitar_agendamento') {
    const petId = Number(dados.pet_id || 0);
    const servicoIds = Array.isArray(dados.servico_ids) ? [...new Set(dados.servico_ids.map(Number).filter(Boolean))] : [];
    const data = String(dados.data || '');
    const horario = String(dados.horario || '');
    const duracao = Number(dados.duracao_minutos || 60);
    const pets = await petsDoCliente(clienteId);
    if (!pets.some((pet: any) => Number(pet.id) === petId)) throw new Error('O pet selecionado não pertence a este tutor.');
    if (!servicoIds.length) throw new Error('Selecione pelo menos um serviço.');

    const disponiveis = await horariosDisponiveis(data, servicoIds, duracao);
    if (!disponiveis.includes(horario)) throw new Error('Esse horário acabou de ficar indisponível. Escolha outro.');

    const { data: agendamento, error } = await supabase.from('agendamentos').insert({
      pet_id: petId,
      data_hora_inicio: dataHoraLocal(data, horario),
      data_hora_fim: somarMinutosLocal(data, horario, duracao),
      origem: 'MOBILE',
      status: 'SOLICITADO',
      observacoes_cliente: String(dados.observacoes_cliente || '').trim() || null,
    }).select('id').single();
    if (error || !agendamento) throw new Error(mensagemErro(error, 'Não foi possível enviar a solicitação.'));

    const itens = servicoIds.map((servicoId) => ({ agendamento_id: agendamento.id, servico_id: servicoId }));
    const { error: erroItens } = await supabase.from('agendamento_itens').insert(itens);
    if (erroItens) {
      await supabase.from('agendamentos').delete().eq('id', agendamento.id);
      throw new Error(mensagemErro(erroItens, 'Não foi possível vincular os serviços ao agendamento.'));
    }

    const pet = pets.find((item: any) => Number(item.id) === petId);
    await supabase.from('notificacoes_mobile').insert({
      cliente_id: clienteId,
      pet_id: petId,
      agendamento_id: agendamento.id,
      tipo: 'AGENDAMENTO',
      titulo: 'Solicitação enviada',
      mensagem: `Sua solicitação para ${pet?.nome || 'o pet'} foi enviada ao Beauty Pet e aguarda confirmação.`,
    });

    return { sucesso: true, mensagem: 'O Beauty Pet recebeu a sua solicitação de agendamento.', agendamento_id: Number(agendamento.id) } as T;
  }

  if (acao === 'agenda_pessoal') {
    const pets = await petsDoCliente(clienteId);
    const petMap = new Map(pets.map((pet: any) => [Number(pet.id), pet.nome]));
    const petIds = pets.map((pet: any) => Number(pet.id));

    const { data: pessoais, error: erroPessoais } = await supabase
      .from('eventos_agenda_mobile')
      .select('id,titulo,descricao,data_hora_inicio,data_hora_fim,status,pet_id,categoria_id')
      .eq('cliente_id', clienteId)
      .neq('status', 'CANCELADO');
    if (erroPessoais) throw new Error(mensagemErro(erroPessoais));

    const categoriaIds = [...new Set((pessoais || []).map((item: any) => Number(item.categoria_id)).filter(Boolean))];
    const categoriaMap = new Map<number, string>();
    if (categoriaIds.length) {
      const { data } = await supabase.from('categorias_evento_mobile').select('id,nome').in('id', categoriaIds);
      (data || []).forEach((item: any) => categoriaMap.set(Number(item.id), item.nome));
    }

    let comerciais: any[] = [];
    if (petIds.length) {
      const { data, error } = await supabase
        .from('agendamentos')
        .select('id,pet_id,data_hora_inicio,data_hora_fim,status,observacoes_cliente')
        .in('pet_id', petIds)
        .not('status', 'in', '("RECUSADO","CANCELADO")');
      if (error) throw new Error(mensagemErro(error));
      const nomes = await nomesServicosPorAgendamento((data || []).map((item: any) => Number(item.id)));
      comerciais = (data || []).map((item: any) => ({
        id: -Number(item.id),
        titulo: (nomes.get(Number(item.id)) || ['Agendamento Beauty Pet']).join(' + '),
        descricao: item.observacoes_cliente,
        data_hora_inicio: item.data_hora_inicio,
        data_hora_fim: item.data_hora_fim,
        status: item.status,
        pet_id: Number(item.pet_id),
        pet: petMap.get(Number(item.pet_id)) || null,
        categoria: 'Beauty Pet',
        origem: 'BEAUTY_PET',
        referencia_id: Number(item.id),
      }));
    }

    const eventos = [
      ...comerciais,
      ...(pessoais || []).map((item: any) => ({
        ...item,
        id: Number(item.id),
        pet: item.pet_id ? petMap.get(Number(item.pet_id)) || null : null,
        categoria: item.categoria_id ? categoriaMap.get(Number(item.categoria_id)) || null : null,
        origem: 'PESSOAL',
        referencia_id: Number(item.id),
      })),
    ].sort((a, b) => String(a.data_hora_inicio).localeCompare(String(b.data_hora_inicio)));

    return { sucesso: true, eventos } as T;
  }

  if (acao === 'criar_evento') {
    const titulo = String(dados.titulo || '').trim();
    const descricao = String(dados.descricao || '').trim();
    const data = String(dados.data || '');
    const horario = String(dados.horario || '');
    // HEURÍSTICA 9: uma mensagem por campo.
    if (!titulo) throw new Error('Informe um título para o evento.');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) throw new Error('Informe a data no formato DD/MM/AAAA.');
    if (!/^\d{2}:\d{2}$/.test(horario)) throw new Error('Informe o horário no formato HH:MM (ex.: 14:30).');
    // HEURÍSTICA 9: não deixa criar evento num horário que já passou.
    const [ano, mes, dia] = data.split('-').map(Number);
    const [hora, minuto] = horario.split(':').map(Number);
    if (new Date(ano, mes - 1, dia, hora, minuto).getTime() < Date.now() - 60_000) {
      throw new Error('Esse horário já passou. Escolha uma data e horário a partir de agora.');
    }

    let categoriaId = dados.categoria_id ? Number(dados.categoria_id) : null;
    if (!categoriaId) {
      const { data: categoria } = await supabase.from('categorias_evento_mobile').select('id').eq('nome', 'Outros').maybeSingle();
      categoriaId = categoria ? Number(categoria.id) : null;
    }

    const petId = dados.pet_id ? Number(dados.pet_id) : null;
    if (petId) {
      const pets = await petsDoCliente(clienteId);
      if (!pets.some((pet: any) => Number(pet.id) === petId)) throw new Error('O pet selecionado não pertence a este tutor.');
    }

    const { data: evento, error } = await supabase.from('eventos_agenda_mobile').insert({
      cliente_id: clienteId,
      pet_id: petId,
      categoria_id: categoriaId,
      titulo,
      descricao: descricao || null,
      data_hora_inicio: dataHoraLocal(data, horario),
      status: 'AGENDADO',
    }).select('id').single();
    if (error) throw new Error(mensagemErro(error, 'Não foi possível criar o evento.'));
    return { sucesso: true, mensagem: 'Evento criado com sucesso.', evento_id: Number(evento.id) } as T;
  }

  if (acao === 'excluir_evento') {
    const id = Number(dados.evento_id || 0);
    if (!id) throw new Error('Evento inválido.');
    const { error } = await supabase
      .from('eventos_agenda_mobile')
      .update({ status: 'CANCELADO' })
      .eq('id', id)
      .eq('cliente_id', clienteId);
    if (error) throw new Error(mensagemErro(error));
    return { sucesso: true } as T;
  }

  if (acao === 'categorias_gasto') {
    const { data, error } = await supabase.from('categorias_gasto_mobile').select('id,nome').eq('ativo', true).order('nome');
    if (error) throw new Error(mensagemErro(error));
    return { sucesso: true, categorias: data || [] } as T;
  }

  if (acao === 'adicionar_gasto') {
    const categoriaId = Number(dados.categoria_id || 0);
    const petId = dados.pet_id ? Number(dados.pet_id) : null;
    const descricao = String(dados.descricao || '').trim();
    const valor = numeroDigitado(dados.valor);
    const dataGasto = String(dados.data_gasto || '');
    // HEURÍSTICA 9: antes uma só mensagem para 4 campos, e "12,50" virava NaN e chegava ao banco.
    if (!categoriaId) throw new Error('Selecione a categoria do gasto.');
    if (!descricao) throw new Error('Descreva o gasto (ex.: Ração 10 kg).');
    if (!Number.isFinite(valor) || valor <= 0) throw new Error('Informe um valor maior que zero (ex.: 12,50).');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dataGasto)) throw new Error('Informe a data do gasto no formato DD/MM/AAAA.');
    const { data: categoria, error: erroCategoria } = await supabase
      .from('categorias_gasto_mobile')
      .select('id,nome')
      .eq('id', categoriaId)
      .eq('ativo', true)
      .maybeSingle();
    if (erroCategoria || !categoria) throw new Error('A categoria selecionada é inválida.');
    if (dados.categoria_nome && normalizarTexto(dados.categoria_nome) !== normalizarTexto(categoria.nome)) {
      throw new Error('A categoria selecionada não corresponde ao cadastro do banco. Atualize a tela e tente novamente.');
    }
    if (petId) {
      const pets = await petsDoCliente(clienteId);
      if (!pets.some((pet: any) => Number(pet.id) === petId)) throw new Error('O pet selecionado não pertence a este tutor.');
    }
    const { data: gasto, error } = await supabase.from('gastos_pessoais_mobile').insert({
      cliente_id: clienteId,
      pet_id: petId,
      categoria_id: categoriaId,
      descricao,
      valor,
      data_gasto: dataGasto,
    }).select('id').single();
    if (error) throw new Error(mensagemErro(error, 'Não foi possível adicionar o gasto.'));
    return { sucesso: true, mensagem: 'Gasto adicionado.', gasto_id: Number(gasto.id) } as T;
  }

  if (acao === 'resumo_financeiro') {
    const ano = Number(dados.ano || new Date().getFullYear());
    const mes = Number(dados.mes || new Date().getMonth() + 1);
    const { inicio, fim } = inicioFimMes(ano, mes);
    const [{ data: gastos, error: erroGastos }, { data: categorias, error: erroCategorias }, { data: budget, error: erroBudget }] = await Promise.all([
      supabase.from('gastos_pessoais_mobile').select('categoria_id,valor').eq('cliente_id', clienteId).gte('data_gasto', inicio).lt('data_gasto', fim),
      supabase.from('categorias_gasto_mobile').select('id,nome').eq('ativo', true).order('nome'),
      supabase.from('vw_mobile_budget').select('valor_total').eq('cliente_id', clienteId).eq('ano', ano).eq('mes', mes),
    ]);
    if (erroGastos || erroCategorias || erroBudget) throw new Error(mensagemErro(erroGastos || erroCategorias || erroBudget));

    const totalPessoal = (gastos || []).reduce((soma: number, item: any) => soma + Number(item.valor || 0), 0);
    const categoriasResumo = (categorias || []).map((categoria: any) => ({
      id: Number(categoria.id),
      nome: categoria.nome,
      total: (gastos || [])
        .filter((gasto: any) => Number(gasto.categoria_id) === Number(categoria.id))
        .reduce((soma: number, gasto: any) => soma + Number(gasto.valor || 0), 0),
    }));
    const totalBudget = (budget || []).reduce((soma: number, item: any) => soma + Number(item.valor_total || 0), 0);
    const pets = await petsDoCliente(clienteId);

    return {
      sucesso: true,
      ano,
      mes,
      total_pessoal: totalPessoal,
      categorias: categoriasResumo,
      budget: { total: totalBudget, quantidade: (budget || []).length },
      total_geral: totalPessoal + totalBudget,
      primeiro_pet: pets[0]?.nome || null,
    } as T;
  }

  if (acao === 'budget') {
    const ano = Number(dados.ano || new Date().getFullYear());
    const mes = Number(dados.mes || new Date().getMonth() + 1);
    const { data, error } = await supabase
      .from('vw_mobile_budget')
      .select('data_fechamento,pet_id,pet,servico,descricao,valor_servico,total_adicionais,valor_total')
      .eq('cliente_id', clienteId)
      .eq('ano', ano)
      .eq('mes', mes)
      .order('data_fechamento', { ascending: false })
      .order('pet')
      .order('servico');
    if (error) throw new Error(mensagemErro(error, 'Não foi possível carregar os gastos do Beauty Pet.'));
    return { sucesso: true, itens: (data || []).map((item: any) => ({ ...item, valor_servico: Number(item.valor_servico || 0), total_adicionais: Number(item.total_adicionais || 0), valor_total: Number(item.valor_total || 0) })) } as T;
  }

  if (acao === 'notificacoes') {
    await sincronizarNotificacoes(clienteId);
    const { data, error } = await supabase
      .from('notificacoes_mobile')
      .select('id,titulo,mensagem,tipo,lida,criado_em')
      .eq('cliente_id', clienteId)
      .order('criado_em', { ascending: false })
      .order('id', { ascending: false });
    if (error) throw new Error(mensagemErro(error));
    return { sucesso: true, notificacoes: (data || []).map((item: any) => ({ ...item, id: Number(item.id), lida: Boolean(item.lida) })) } as T;
  }

  if (acao === 'marcar_notificacao_lida') {
    const id = Number(dados.notificacao_id || 0);
    if (id) {
      const { error } = await supabase
        .from('notificacoes_mobile')
        .update({ lida: true, lida_em: new Date().toISOString() })
        .eq('id', id)
        .eq('cliente_id', clienteId);
      if (error) throw new Error(mensagemErro(error));
    }
    return { sucesso: true } as T;
  }

  if (acao === 'limpar_notificacoes') {
    const { error } = await supabase.from('notificacoes_mobile').delete().eq('cliente_id', clienteId);
    if (error) throw new Error(mensagemErro(error));
    return { sucesso: true } as T;
  }

  throw new Error(`Ação não implementada no Supabase: ${acao}`);
}
