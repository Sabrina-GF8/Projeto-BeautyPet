import {
  ActivityIndicator,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import {
  ArrowRight,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Home,
  Plus,
  UserRound,
  Wallet,
} from 'lucide-react-native';

import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import Svg, {
  Circle,
  Text as SvgText,
} from 'react-native-svg';

import { api } from '../lib/api';

type CategoriaResumo = {
  id: number;
  nome: string;
  total: number;
};

type CategoriaGrafico = {
  nome: string;
  total: number;
  cor: string;
};

const PALETA = [
  '#FF5A5F',
  '#FFAD43',
  '#62C9AA',
  '#6BA8FF',
  '#9B8AFB',
  '#F27CB3',
];

const CORES_CATEGORIAS: Record<string, string> = {
  higiene: '#FF5A5F',
  saude: '#62C9AA',
  alimentacao: '#FFAD43',
  lazer: '#6BA8FF',
  outro: '#9B8AFB',
  outros: '#9B8AFB',
  beautypet: '#5d57fb',
};

const NOMES_MESES = [
  'Janeiro',
  'Fevereiro',
  'Março',
  'Abril',
  'Maio',
  'Junho',
  'Julho',
  'Agosto',
  'Setembro',
  'Outubro',
  'Novembro',
  'Dezembro',
];

function normalizarNome(valor: string) {
  return valor
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '')
    .toLowerCase();
}

function moeda(valor: number) {
  return Number(valor || 0).toLocaleString(
    'pt-BR',
    {
      style: 'currency',
      currency: 'BRL',
    }
  );
}

function corDaCategoria(
  nome: string,
  indice: number
) {
  return (
    CORES_CATEGORIAS[normalizarNome(nome)] ||
    PALETA[indice % PALETA.length]
  );
}

function GraficoRosca({
  categorias,
  total,
}: {
  categorias: CategoriaGrafico[];
  total: number;
}) {
  const tamanho = 160;
  const espessura = 28;
  const centro = tamanho / 2;
  const raio = (tamanho - espessura) / 2;
  const circunferencia = 2 * Math.PI * raio;

  let acumulado = 0;

  const segmentos = categorias.map(
    (categoria) => {
      const proporcao =
        total > 0
          ? categoria.total / total
          : 0;

      const comprimento =
        proporcao * circunferencia;

      const inicio = acumulado;

      const anguloInicio =
        (inicio / circunferencia) *
          2 *
          Math.PI -
        Math.PI / 2;

      const anguloFinal =
        ((inicio + comprimento) /
          circunferencia) *
          2 *
          Math.PI -
        Math.PI / 2;

      const anguloMeio =
        (anguloInicio + anguloFinal) / 2;

      acumulado += comprimento;

      return {
        ...categoria,
        proporcao,
        inicio,
        comprimento,

        textoX:
          centro +
          raio * Math.cos(anguloMeio),

        textoY:
          centro +
          raio * Math.sin(anguloMeio) +
          3,
      };
    }
  );

  return (
    <View style={styles.graficoContainer}>
      <Svg
        width={tamanho}
        height={tamanho}
      >
        <Circle
          cx={centro}
          cy={centro}
          r={raio}
          fill="none"
          stroke="#EEEEEE"
          strokeWidth={espessura}
        />

        {segmentos.map(
          (segmento, indice) => {
            const comprimentoVisivel =
              Math.max(
                segmento.comprimento - 2,
                0
              );

            return (
              <Circle
                key={`${segmento.nome}-${indice}`}
                cx={centro}
                cy={centro}
                r={raio}
                fill="none"
                stroke={segmento.cor}
                strokeWidth={espessura}
                strokeDasharray={`${comprimentoVisivel} ${circunferencia}`}
                strokeDashoffset={
                  -segmento.inicio
                }
                rotation={-90}
                origin={`${centro}, ${centro}`}
                strokeLinecap="butt"
              />
            );
          }
        )}

        {segmentos
          .filter(
            (segmento) =>
              segmento.proporcao >= 0.08
          )
          .map((segmento, indice) => (
            <SvgText
              key={`porcentagem-${segmento.nome}-${indice}`}
              x={segmento.textoX}
              y={segmento.textoY}
              fill="#FFFFFF"
              fontSize="10"
              fontWeight="600"
              textAnchor="middle"
            >
              {`${Math.round(
                segmento.proporcao * 100
              )}%`}
            </SvgText>
          ))}
      </Svg>

      <View
        style={styles.centroGrafico}
        pointerEvents="none"
      >
        <Text style={styles.chartTotalLabel}>
          total
        </Text>

        <Text style={styles.chartTotal}>
          {moeda(total)}
        </Text>
      </View>
    </View>
  );
}

export default function Financeiro() {
  const router = useRouter();
  const hoje = new Date();

  const [anoAtual, setAnoAtual] =
    useState(hoje.getFullYear());

  const [mesAtual, setMesAtual] =
    useState(hoje.getMonth());

  const [carregando, setCarregando] =
    useState(true);

  const [erro, setErro] = useState('');

  const [resumo, setResumo] = useState({
    total_pessoal: 0,
    total_geral: 0,

    categorias:
      [] as CategoriaResumo[],

    budget: {
      total: 0,
      quantidade: 0,
    },
  });

  const carregarFinanceiro =
    useCallback(async () => {
      try {
        setCarregando(true);
        setErro('');

        const resposta = await api<{
          total_pessoal?: number;
          total_geral?: number;

          categorias?: CategoriaResumo[];

          budget?: {
            total?: number;
            quantidade?: number;
          };
        }>('resumo_financeiro', {
          ano: anoAtual,
          mes: mesAtual + 1,
        });

        const totalPessoal = Number(
          resposta.total_pessoal || 0
        );

        const totalBeautyPet = Number(
          resposta.budget?.total || 0
        );

        setResumo({
          total_pessoal: totalPessoal,

          total_geral: Number(
            resposta.total_geral ??
              totalPessoal +
                totalBeautyPet
          ),

          categorias:
            (
              resposta.categorias || []
            ).map((categoria) => ({
              id: Number(categoria.id),
              nome: categoria.nome,

              total: Number(
                categoria.total || 0
              ),
            })),

          budget: {
            total: totalBeautyPet,

            quantidade: Number(
              resposta.budget
                ?.quantidade || 0
            ),
          },
        });
      } catch (falha) {
        setErro(
          falha instanceof Error
            ? falha.message
            : 'Não foi possível carregar o financeiro.'
        );
      } finally {
        setCarregando(false);
      }
    }, [anoAtual, mesAtual]);

  useFocusEffect(
    useCallback(() => {
      void carregarFinanceiro();
    }, [carregarFinanceiro])
  );

  const categoriasGrafico = useMemo(
    () => {
      const categoriasPessoais =
        resumo.categorias
          .filter(
            (categoria) =>
              categoria.total > 0
          )
          .map(
            (categoria, indice) => ({
              nome: categoria.nome,
              total: categoria.total,

              cor: corDaCategoria(
                categoria.nome,
                indice
              ),
            })
          );

      if (resumo.budget.total > 0) {
        categoriasPessoais.push({
          nome: 'BeautyPet',
          total: resumo.budget.total,

          cor:
            CORES_CATEGORIAS.beautypet,
        });
      }

      return categoriasPessoais;
    },
    [resumo]
  );

  const totalGrafico =
    categoriasGrafico.reduce(
      (total, categoria) =>
        total + categoria.total,
      0
    );

  function mesAnterior() {
    if (mesAtual === 0) {
      setMesAtual(11);

      setAnoAtual(
        (ano) => ano - 1
      );

      return;
    }

    setMesAtual(
      (mes) => mes - 1
    );
  }

  function proximoMes() {
    if (mesAtual === 11) {
      setMesAtual(0);

      setAnoAtual(
        (ano) => ano + 1
      );

      return;
    }

    setMesAtual(
      (mes) => mes + 1
    );
  }

  const descricaoBeautyPet =
    resumo.budget.quantidade === 1
      ? '1 serviço no mês'
      : `${resumo.budget.quantidade} serviços no mês`;

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scroll}
        contentContainerStyle={
          styles.content
        }
        showsVerticalScrollIndicator={
          false
        }
      >
        <View style={styles.header}>
          <View style={styles.headerText}>
            <Text style={styles.title}>
              Financeiro
            </Text>

            <Text style={styles.subtitle}>
              Controle os gastos dos seus pets
            </Text>
          </View>

          <TouchableOpacity
            style={styles.addButton}
            activeOpacity={0.8}
            onPress={() =>
              router.push('/novo_gasto')
            }
          >
            <Plus
              size={23}
              color="#333333"
            />
          </TouchableOpacity>
        </View>

        <View style={styles.monthSelector}>
          <TouchableOpacity
            style={styles.monthArrow}
            onPress={mesAnterior}
          >
            <ChevronLeft
              size={22}
              color="#A7A7A7"
            />
          </TouchableOpacity>

          <Text style={styles.monthText}>
            {NOMES_MESES[mesAtual]}{' '}
            {anoAtual}
          </Text>

          <TouchableOpacity
            style={styles.monthArrow}
            onPress={proximoMes}
          >
            <ChevronRight
              size={22}
              color="#A7A7A7"
            />
          </TouchableOpacity>
        </View>

        <View style={styles.totalCard}>
          <Text style={styles.totalLabel}>
            Total do mês
          </Text>

          {carregando ? (
            <ActivityIndicator
              color="#5d57fb"
              style={
                styles.indicadorTotal
              }
            />
          ) : (
            <Text style={styles.totalValue}>
              {moeda(
                resumo.total_geral
              )}
            </Text>
          )}
        </View>

        <TouchableOpacity
          style={styles.expenseCard}
          activeOpacity={0.85}
          onPress={() =>
            router.push({
              pathname: '/budget',

              params: {
                ano: String(anoAtual),

                mes: String(
                  mesAtual + 1
                ),
              },
            })
          }
        >
          <View style={styles.expenseIcon}>
            <Text style={styles.star}>
              ★
            </Text>
          </View>

          <View style={styles.expenseInfo}>
            <Text
              style={
                styles.expenseCompany
              }
            >
              BeautyPet
            </Text>

            <Text
              style={
                styles.expenseValue
              }
            >
              {moeda(
                resumo.budget.total
              )}
            </Text>

            <Text
              style={
                styles.expenseDescription
              }
            >
              {descricaoBeautyPet}
            </Text>
          </View>

          <ArrowRight
            size={21}
            color="#FFFFFF"
          />
        </TouchableOpacity>

        <View style={styles.chartCard}>
          <Text style={styles.chartTitle}>
            Gastos por categoria
          </Text>

          {carregando ? (
            <View
              style={styles.estadoGrafico}
            >
              <ActivityIndicator color="#5d57fb" />

              <Text
                style={styles.estadoTexto}
              >
                Carregando gastos...
              </Text>
            </View>
          ) : erro ? (
            <View
              style={styles.estadoGrafico}
            >
              <Text
                style={styles.erroTexto}
              >
                {erro}
              </Text>

              <TouchableOpacity
                style={
                  styles.tentarNovamente
                }
                onPress={() =>
                  void carregarFinanceiro()
                }
              >
                <Text
                  style={
                    styles.tentarNovamenteTexto
                  }
                >
                  Tentar novamente
                </Text>
              </TouchableOpacity>
            </View>
          ) : totalGrafico <= 0 ? (
            <View
              style={styles.estadoGrafico}
            >
              <GraficoRosca
                categorias={[]}
                total={0}
              />

              <Text
                style={styles.estadoTexto}
              >
                Nenhum gasto registrado
                neste mês.
              </Text>
            </View>
          ) : (
            <>
              <GraficoRosca
                categorias={
                  categoriasGrafico
                }
                total={totalGrafico}
              />

              <View style={styles.legend}>
                {categoriasGrafico.map(
                  (categoria) => (
                    <View
                      key={categoria.nome}
                      style={
                        styles.legendItem
                      }
                    >
                      <View
                        style={[
                          styles.legendDot,

                          {
                            backgroundColor:
                              categoria.cor,
                          },
                        ]}
                      />

                      <View
                        style={
                          styles.legendText
                        }
                      >
                        <Text
                          style={
                            styles.legendTitle
                          }
                        >
                          {categoria.nome}
                        </Text>

                        <Text
                          style={
                            styles.legendValue
                          }
                        >
                          {moeda(
                            categoria.total
                          )}
                        </Text>
                      </View>
                    </View>
                  )
                )}
              </View>
            </>
          )}
        </View>
      </ScrollView>

      <View style={styles.bottomMenu}>
        <TouchableOpacity
          style={styles.menuItem}
          onPress={() =>
            router.push('/home')
          }
        >
          <Home
            size={24}
            color="#8D8D8D"
          />

          <Text style={styles.menuText}>
            Home
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() =>
            router.push('/agenda')
          }
        >
          <CalendarDays
            size={24}
            color="#8D8D8D"
          />

          <Text style={styles.menuText}>
            Agenda
          </Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.menuItem}
        >
          <Wallet
            size={24}
            color="#5d57fb"
          />

          <Text
            style={
              styles.menuTextActive
            }
          >
            Financeiro
          </Text>

          <View
            style={styles.activeLine}
          />
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() =>
            router.push('/perfil')
          }
        >
          <UserRound
            size={24}
            color="#8D8D8D"
          />

          <Text style={styles.menuText}>
            Perfil
          </Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFF4AB',
  },

  scroll: {
    flex: 1,
  },

  content: {
    paddingHorizontal: 16,
    paddingTop: 36,
    paddingBottom: 105,
  },

  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },

  headerText: {
    flex: 1,
  },

  title: {
    fontSize: 22,
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },

  subtitle: {
    fontSize: 11,
    color: '#666666',
    marginTop: 1,
    fontFamily: 'Poppins_400Regular',
  },

  addButton: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.16,
    shadowRadius: 4,
    elevation: 4,
  },

  monthSelector: {
    height: 42,
    backgroundColor: '#FFFFFF',
    borderRadius: 13,
    marginTop: 16,

    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 1,
    },

    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 2,
  },

  monthArrow: {
    width: 46,
    height: 42,
    justifyContent: 'center',
    alignItems: 'center',
  },

  monthText: {
    fontSize: 12,
    color: '#222222',
    fontFamily: 'Poppins_500Medium',
  },

  totalCard: {
    minHeight: 76,
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginTop: 16,
    justifyContent: 'center',
  },

  totalLabel: {
    fontSize: 11,
    color: '#777777',
    fontFamily: 'Poppins_400Regular',
  },

  totalValue: {
    fontSize: 22,
    color: '#222222',
    marginTop: 2,
    fontFamily: 'Poppins_500Medium',
  },

  indicadorTotal: {
    alignSelf: 'flex-start',
    marginTop: 7,
  },

  expenseCard: {
    minHeight: 82,
    backgroundColor: '#5d57fb',
    borderRadius: 13,
    marginTop: 14,

    flexDirection: 'row',
    alignItems: 'center',

    paddingHorizontal: 12,
  },

  expenseIcon: {
    width: 40,
    height: 42,
    borderRadius: 10,

    backgroundColor:
      'rgba(255,255,255,0.25)',

    justifyContent: 'center',
    alignItems: 'center',
  },

  star: {
    color: '#FFFFFF',
    fontSize: 23,
  },

  expenseInfo: {
    flex: 1,
    marginLeft: 10,
  },

  expenseCompany: {
    color: '#FFFFFF',
    fontSize: 10,
    fontFamily: 'Poppins_400Regular',
  },

  expenseValue: {
    color: '#FFFFFF',
    fontSize: 16,
    marginTop: -1,
    fontFamily: 'Poppins_600SemiBold',
  },

  expenseDescription: {
    color: '#FFFFFF',
    fontSize: 9,
    opacity: 0.9,
    fontFamily: 'Poppins_400Regular',
  },

  chartCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 15,
    marginTop: 14,
    paddingHorizontal: 14,
    paddingTop: 15,
    paddingBottom: 14,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },

  chartTitle: {
    fontSize: 13,
    color: '#222222',
    fontFamily: 'Poppins_500Medium',
  },

  graficoContainer: {
    width: 160,
    height: 160,
    alignSelf: 'center',
    marginTop: 14,
    marginBottom: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },

  centroGrafico: {
    position: 'absolute',
    alignItems: 'center',
    justifyContent: 'center',
    width: 88,
    height: 88,
  },

  chartTotalLabel: {
    fontSize: 9,
    color: '#999999',
    fontFamily: 'Poppins_400Regular',
  },

  chartTotal: {
    fontSize: 12,
    color: '#333333',
    fontFamily: 'Poppins_600SemiBold',
  },

  estadoGrafico: {
    minHeight: 205,
    justifyContent: 'center',
    alignItems: 'center',
  },

  estadoTexto: {
    marginTop: 9,
    fontSize: 11,
    color: '#777777',
    textAlign: 'center',
    fontFamily: 'Poppins_400Regular',
  },

  erroTexto: {
    fontSize: 11,
    color: '#B42318',
    textAlign: 'center',
    fontFamily: 'Poppins_400Regular',
  },

  tentarNovamente: {
    marginTop: 12,
    borderRadius: 10,
    backgroundColor: '#FFF4AB',
    paddingHorizontal: 13,
    paddingVertical: 8,
  },

  tentarNovamenteTexto: {
    fontSize: 11,
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },

  legend: {
    flexDirection: 'row',
    flexWrap: 'wrap',
  },

  legendItem: {
    width: '50%',
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 11,
  },

  legendDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 7,
  },

  legendText: {
    flex: 1,
  },

  legendTitle: {
    fontSize: 10,
    color: '#555555',
    fontFamily: 'Poppins_500Medium',
  },

  legendValue: {
    fontSize: 8,
    color: '#999999',
    fontFamily: 'Poppins_400Regular',
  },

  bottomMenu: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,

    height: 75,
    backgroundColor: '#FFFFFF',

    borderTopWidth: 1,
    borderTopColor: '#E5E5E5',

    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
  },

  menuItem: {
    flex: 1,
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },

  menuText: {
    marginTop: 3,
    fontSize: 11,
    color: '#8D8D8D',
    fontFamily: 'Poppins_400Regular',
  },

  menuTextActive: {
    marginTop: 3,
    fontSize: 11,
    color: '#5d57fb',
    fontFamily: 'Poppins_600SemiBold',
  },

  activeLine: {
    position: 'absolute',
    bottom: 0,
    width: 35,
    height: 3,
    borderRadius: 3,
    backgroundColor: '#5d57fb',
  },
});