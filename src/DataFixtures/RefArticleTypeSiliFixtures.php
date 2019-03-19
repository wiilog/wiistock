<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\TypeRepository;

class RefArticleTypeSiliFixtures extends Fixture
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;

    public function __construct(UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository)
    {
        $this->encoder = $encoder;
        $this->typeRepository = $typeRepository;
    }

    public function load(ObjectManager $manager)
    {
        $refArticleSiliS = [
            ['ref' => 'SIL_100_001', 'designation' => ' SILICIUM_P_MONITOR_CZ_<100>_525UM_14-20OHM', ' stock' => 106],
            ['ref' => 'SIL_100_002', 'designation' => '	SIL_N+_MONITOR_<111>_525UM_1-4MILLIOHM_TTV<12UM', ' stock' =>     69],
            ['ref' => 'SIL_100_004 ', 'designation' => '	SILCIUM_P+_MONITOR_CZ_<100>_525UM_0,01-0,02Ohms', ' stock' =>         124],
            ['ref' => 'SIL_100_005A', 'designation' => '	SIL N+<100>525µm 0.001-0.004Ohms', ' stock' =>         130],
            ['ref' => 'SIL_100_007', 'designation' => '	SI P/B<100> 515 µm 10-20 Ohms DSP', ' stock' =>         25],
            ['ref' => 'SIL_100_008', 'designation' => '	SI_100_SOI_340_400NM', ' stock' =>         17],
            ['ref' => 'SIL_200_001', 'designation' => '	SILICIUM_P_MONITOR_CZ_<110>_725UM_6-12OHM_TTV<5UM', ' stock' =>         46],
            ['ref' => 'SIL_200_002', 'designation' => '	SIL_P_MONITOR_CZ_<100>_550UM_5-20OHM_DSP_TTV<2.5UM', ' stock' =>         126],
            ['ref' => 'SIL_200_003', 'designation' => '	SILIC_P_MONITOR_CZ_<100>_550UM_5-20OHM_DSP_TTV<1UM', ' stock' =>         807],
            ['ref' => 'SIL_200_005', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-50OHM', ' stock' =>         2052],
            ['ref' => 'SIL_200_005', 'designation' => ' Sas	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-50OHM', ' stock' =>         150],
            ['ref' => 'SIL_200_005', 'designation' => ' STOCK RIVES	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-50OHM', ' stock' =>         6700],
            ['ref' => 'SIL_200_006', 'designation' => '	SILICIUM_P_PRIME_CZ_<100>_725UM_5-10OHM', ' stock' =>         1138],
            ['ref' => 'SIL_200_008', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE1_MINI-660UM', ' stock' =>         92],
            ['ref' => 'SIL_200_008', 'designation' => ' SAS	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE1_MINI-660UM', ' stock' =>         0],
            ['ref' => 'SIL_200_009', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE2_MINI-660UM', ' stock' =>         183],
            ['ref' => 'SIL_200_010', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE3_MINI-630UM', ' stock' =>         1642],
            ['ref' => 'SIL_200_012', 'designation' => ' STOCK RIVES	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE1_MINI-660UM', ' stock' =>         700],
            ['ref' => 'SIL_200_012', 'designation' => '	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE1_MINI-660UM', ' stock' =>         345],
            ['ref' => 'SIL_200_013', 'designation' => '	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE2_MINI-660UM', ' stock' =>     0],
            ['ref' => 'SIL_200_014', 'designation' => '	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE3_MINI-630UM', ' stock' =>         0],
            ['ref' => 'SIL_200_015', 'designation' => '	SILIC_P_MONITOR_CZ_<100>_725UM_5-20OHM_DSP_TTV<1UM', ' stock' =>        668],
            ['ref' => 'SIL_200_017', 'designation' => '	SIL_P+_FR_MONITOR_CZ_<100>_725UM_0.01-0.02OHM_DSP', ' stock' =>        1029],
            ['ref' => 'SIL_200_018', 'designation' => '	SILICIUM_P-_HR_PRIME_CZ_<100>_725UM_1000-99999OHM', ' stock' =>        581],
            ['ref' => 'SIL_200_019', 'designation' => '	SIL_P_MONITOR_ULTRAFLAT_CZ_<100>_725UM_10000-50.000 ohm_DSP', ' stock' =>        120],
            ['ref' => 'SIL_200_024', 'designation' => '	SILICIUM_N_PRIME_CZ_<100>_3-6OHM_725UM', ' stock' =>        352],
            ['ref' => 'SIL_200_026', 'designation' => '	SILICIUM_P_MONITOR_CZ_<111>_725UM_6-12OHM', ' stock' =>        396],
            ['ref' => 'SIL_200_029A', 'designation' => '	SILIC_N+_FR_MONITOR_CZ_<100>_725UM_<3MILLIOHM_DSP', ' stock' =>        251],
            ['ref' => 'SIL_200_030A', 'designation' => '	SILICIUM_N_BSOI_CZ_<100>_25000NM-4000NM', ' stock' =>        20],
            ['ref' => 'SIL_200_031', 'designation' => '	SILICIUM_P_BSOI_CZ_<100>_20000NM-2000NM', ' stock' =>        5],
            ['ref' => 'SIL_200_032', 'designation' => '	SOI_MONITOR_70NM-145NM_P_<100>_725UM_8.5-11.5OHM', ' stock' =>        82],
            ['ref' => 'SIL_200_033', 'designation' => '	SOI_PRIME_70NM-145NM_P_CZ_<100>_725UM_8.5-11.5OHM', ' stock' =>        23],
            ['ref' => 'SIL_200_034', 'designation' => '	SOI_PRIME_340NM-2000NM_P_<100>_725UM_8.5-11.5OHM', ' stock' =>        549],
            ['ref' => 'SIL_200_035A', 'designation' => '	SOI_PRIME_160NM-400NM_P-_HR_<100>_725UM_>1000OHM', ' stock' =>        130],
            ['ref' => 'SIL_200_037', 'designation' => '	SOI_PRIME_205NM-400NM_P_CZ_<100>_725UM_8.5-11.5OHM', ' stock' =>        103],
            ['ref' => 'SIL_200_039', 'designation' => '	SOI_PRIME_100NM-200NM_P_CZ_<100>_725UM_8.5-11.5OHM', ' stock' =>        59],
            ['ref' => 'SIL_200_040', 'designation' => '	SOI_PRIM_400NM-1000NM_P_CZ_<100>_725UM_8.5-11.5OHM', ' stock' =>        147],
            ['ref' => 'SIL_200_041', 'designation' => '	SOI_PRIM_400NM-2000NM_P_CZ_<100>_725UM_8.5-11.5OHM', ' stock' =>        73],
            ['ref' => 'SIL_200_042', 'designation' => ' STOCK RIVES	SOI_PRIME_P_20%_145A-1450A_CZ_<100>_725UM', ' stock' =>        23],
            ['ref' => 'SIL_200_043', 'designation' => ' STOCK RIVES	SOI_PRIME_P_30%_120A-1450A_CZ_<100>_725UM', ' stock' =>        3],
            ['ref' => 'SIL_200_044', 'designation' => ' STOCK RIVES	SOI_PRIME_P_40%_90A-1450A_CZ_<100>_725UM', ' stock' =>        9],
            ['ref' => 'SIL_200_046A', 'designation' => '	BSOI_PRIME_P_12000NM-500NM_CZ_<100>_725UM', ' stock' =>        32],
            ['ref' => 'SIL_200_047A', 'designation' => '	BSOI_PRIME_P_27000NM-500NM_CZ_<100>_725UM', ' stock' =>        51],
            ['ref' => 'SIL_200_050', 'designation' => '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM_MARQUE', ' stock' =>        151],
            ['ref' => 'SIL_200_060A', 'designation' => '	SILICIUM_P_MONITOR_CZ_<111>_1000UM_3-20OHM', ' stock' =>        901],
            ['ref' => 'SIL_200_062', 'designation' => ' UNIQUEMENT POUR DUPRE	SOI_PRIME_220NM-2000NM_P_CZ_<100>_725UM_14-18.9OHM', ' stock' =>        39],
            ['ref' => 'SIL_200_078', 'designation' => '	VERRE_Eagle XG_700UM_DSP_MARQUE', ' stock' =>        358],
            ['ref' => 'SIL_200_078A', 'designation' => '	VERRE_Eagle XG_700UM_DSP_MARQUE', ' stock' =>        49],
            ['ref' => 'SIL_200_079A', 'designation' => '	SILICIUM_P_MONITOR-2-SUP-QUAL_<100>_725UM_1-50OHM', ' stock' =>        925],
            ['ref' => 'SIL_200_085', 'designation' => '	SILICIUM_N+_ANTIMONY_CZ_<100>_725UM_0,01-0,02OHM', ' stock' =>        20],
            ['ref' => 'SIL_200_086A', 'designation' => '	SILICIUM_P_MONITOR_CZ_<111>_1000UM_1-1500OHM', ' stock' =>        5],
            ['ref' => 'SIL_200_087', 'designation' => '	SILIC_P+_FR_MONITOR_CZ_<100>_725UM - 0.01-0.02OHM', ' stock' =>        324],
            ['ref' => 'SIL_200_088', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_1000UM_1-20OHM', ' stock' =>        7],
            ['ref' => 'SIL_200_090A', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_725UM_20-30OHM_TTV<3UM', ' stock' =>        15],
            ['ref' => 'SIL_200_093A', 'designation' => '	SIL_N++_RED-PH_MONITOR_<100>_725UM_10-20MILLIOHM', ' stock' =>        22],
            ['ref' => 'SIL_200_097A', 'designation' => '	BSOI_PRIME_18UM-1,5UM', ' stock' =>        5],
            ['ref' => 'SIL_200_099', 'designation' => '	SIL_N+_RED_PH_MONITOR_<100>_1000UM_1.2-1.5MILLIOHM', ' stock' =>        136],
            ['ref' => 'SIL_200_101A', 'designation' => '	SOI_PRIME_600NM', ' stock' =>        30],
            ['ref' => 'SIL_200_102A', 'designation' => '	SOI_PRIME_310NM-800NM_P_CZ_<100>_725UM_>750OHM', ' stock' =>        177],
            ['ref' => 'SIL_200_104B', 'designation' => '	BSOI_N_CZ_<100>_1.5UM-2UM_725UM_5-15OHM', ' stock' =>        28],
            ['ref' => 'SIL_200_105A', 'designation' => '	BSOI_N_CZ_<100>_0.5UM-100UM_725UM_5-15OHM', ' stock' =>        81],
            ['ref' => 'SIL_200_109A', 'designation' => '	BSOI_P+_<100>_1.5UM-10UM_<100>_725UM_0.01-0.025OHM', ' stock' =>        25],
            ['ref' => 'SIL_200_111A', 'designation' => '	SOI_PRIME_HR_220NM-2000NM+-100A_>750OHM', ' stock' =>        169],
            ['ref' => 'SIL_200_113', 'designation' => '	SIL_N+_RED_PH_MONITOR_CZ_<100>_725UM_<1,6MILLIOHM', ' stock' =>        81],
            ['ref' => 'SIL_200_115', 'designation' => '	SIL_RECYCLE-NON-CONTAMINE_SUPPLY_GRADE1_MINI-660UM', ' stock' =>        35],
            ['ref' => 'SIL_200_115SAS', 'designation' => '	SIL_RECYCLE-NON-CONTAMINE_SUPPLY_GRADE1_MINI-660UM', ' stock' =>        50],
            ['ref' => 'SIL_200_116', 'designation' => '	SOI_PRIME_500-1000NM+/-100A _P-_HR_725UM 	', ' stock' =>    84],
            ['ref' => 'SIL_200_117', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-5000 OHM ', ' stock' =>        1],
            ['ref' => 'SIL_200_118', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_1000µm_5-20mOhm ', ' stock' =>        72],
            ['ref' => 'SIL_200_120A', 'designation' => '	SIL_P_HR_PRIME_CZ_<100>_725UM_>1000/99999OHM/DSP ', ' stock' =>        19],
            ['ref' => 'SIL_200_120c', 'designation' => '	SIL_P_HR_PRIME_CZ_<100>_725UM_>1000/99999OHM/DSP ', ' stock' =>        11],
            ['ref' => 'SIL_200_121A', 'designation' => '	SILICIUM_P_EPI_7µm 	', ' stock' =>    69],
            ['ref' => 'SIL_200_124A', 'designation' => '	200MM-SILICIUM-N-5-25OHMS-DSP ET MESURE µPCF', ' stock' =>        1],
            ['ref' => 'SIL_200_126A', 'designation' => '	SILICIUM_P+_CZ_<100>_725UM_8-15MILLIOHM_POLY/LTO ', ' stock' =>        53],
            ['ref' => 'SIL_200_127', 'designation' => '	SIL_N+_RED_PHOS_725µm_1.0-2.0 Mohm', ' stock' =>        556],
            ['ref' => 'SIL_200_128', 'designation' => '	SIL_N+_RED_PH_1000µm ', ' stock' =>        300],
            ['ref' => 'SIL_200_129', 'designation' => '	VERRE_EAGLE XG_500UM	', ' stock' =>    50],
            ['ref' => 'SIL_200_130', 'designation' => '	SIL_N+_725UM_As_3-7MILLIOHM	', ' stock' =>    49],
            ['ref' => 'SIL_200_133A', 'designation' => '	SILICIUM P+<111> 1500µm 0.1-8 mohm	', ' stock' =>    35],
            ['ref' => 'SIL_200_134A', 'designation' => '	SIL 200MM TYPE <111> 1000µm <5mOhm Edge 32R20	', ' stock' =>    20],
            ['ref' => 'SIL_200_137A', 'designation' => '	SIL P<111> 1150µMCM 3000-8000OHMCM	', ' stock' =>    33],
            ['ref' => 'SIL_200_138', 'designation' => '	SIL P <001>OFF Orient 0.4 miscut towards <111>	', ' stock' =>    100],
            ['ref' => 'SIL_200_139', 'designation' => '	WAF.RAW_200MM_BSOI_PP+_CZ_<100>_5UM_1UM_725UM_>100	', ' stock' =>    44],
            ['ref' => 'SIL_200_140A', 'designation' => '	VERRE_BOROFLOAT33_500UM_DSP_TTV<3UM	', ' stock' =>    82],
            ['ref' => 'SIL_200_141A', 'designation' => '	SIL_N+_ARSENIC_<111>_1.5-5MOHM	', ' stock' =>    395],
            ['ref' => 'SIL_200_142A', 'designation' => '	BSOI_P_CZ_(100)_725UM_BOX2000NMLAYER-THICKNE-130UM', ' stock' =>        25],
            ['ref' => 'SIL_200_143A', 'designation' => '	BSOI_P_CZ_(100)_725UM_BOX1500NMLAYER-THICKNES-17UM	', ' stock' =>    75],
            ['ref' => 'SIL_200_146', 'designation' => '	VERRE_200_BOROFLOAT_700µm	', ' stock' =>    27],
            ['ref' => 'SIL_200_147A', 'designation' => '	SI 200 SOI N 1.5-2µm CZ<100> 725µm 5-10OHMS	', ' stock' =>    75],
            ['ref' => 'SIL_200_149A', 'designation' => '	SI 200 SOI P 1.5µm-17µm<100> 725µm	', ' stock' =>    74],
            ['ref' => 'SIL_200_150A', 'designation' => '	SI 200 SOI P 1.5µm-3µm <100> 725µm 5-10 OHMS	', ' stock' =>    22],
            ['ref' => 'SIL_200_151A', 'designation' => '	200mm_SILICIUM_P_MONITOR_CZ<100>550µm_5-20_OHM SSP	', ' stock' =>    13],
            ['ref' => 'SIL_200_152', 'designation' => '	200mm VERRE BOROFLOAT33 725µm DSP TTV < 5µm MARQUE	', ' stock' =>    319],
            ['ref' => 'SIL_200_152A', 'designation' => '	200mm VERRE BOROFLOAT33 725µm DSP TTV < 5µm MARQUE	', ' stock' =>    150],
            ['ref' => 'SIL_200_153', 'designation' => '	SI_200_SOI_60-4µm_CZ_724µm_0.01-0.02_OHM.CM	', ' stock' =>    42],
            ['ref' => 'SIL_200_156A', 'designation' => '	SIL_200_PHOSPHORE<100>725µm_10.61-11.62-OHMS*CM	', ' stock' =>    25],
            ['ref' => 'SIL_200_157A', 'designation' => '	VERRE BOROFLOAT33-1000µm TTV<3µm', ' stock' =>        23],
            ['ref' => 'SIL_200_158A', 'designation' => '	200MM_(111)_1000µm>6§KOHM.CM	', ' stock' =>    47],
            ['ref' => 'SIL_200_159A', 'designation' => '	200MM_(111)_725µm>5KOHM.CM', ' stock' =>        14],
            ['ref' => 'SIL_200_160A', 'designation' => '	200MMSOI1500NM-17µm 568µm 5-15 OHMCM', ' stock' =>        64],
            ['ref' => 'SIL_200_161', 'designation' => '	SOI_P/B(100)625µm_0.5-50µm_N/P(100)10-20_OHMCM', ' stock' =>        25],
            ['ref' => 'SIL_200_162', 'designation' => '	SOI_P/B(100)625µm_0.5-100µm_N/P(100)10-20_OHMCM	', ' stock' =>    50],
            ['ref' => 'SIL_200_163', 'designation' => '	SIL_N+_ARSENIC_<111>_1.5-5OHM_DEPOT_ALN	', ' stock' =>    200],
            ['ref' => 'SIL_200_164', 'designation' => '	SIL_(100)725µm 1-2OHM*CM TTV3.5µm	', ' stock' =>    3],
            ['ref' => 'SIL_200_165', 'designation' => '	SOI 90µm-1µm_725µm_725-375_OHMS	', ' stock' =>    21],
            ['ref' => 'SIL_200_166', 'designation' => '	SOI 1µm BOX 1µm	', ' stock' =>    11],
            ['ref' => 'SIL_200_167', 'designation' => '	SOI 2µm-1µm_725_1-30_OHMS	', ' stock' =>    9],
            ['ref' => 'SIL_200_168', 'designation' => '	SOI 145NM-1000NM_725µm	', ' stock' =>    14],
            ['ref' => 'SIL_200_169', 'designation' => '	SOI 1000NM-1000NM_725µm	', ' stock' =>    4],
            ['ref' => 'SIL_200_170', 'designation' => '	SOI 60µm-1µm_CZ_(100)725µm_750-375OHM	', ' stock' =>    15],
            ['ref' => 'SIL_200_171', 'designation' => '	SOI 160NM-400NM_725µm_HR	', ' stock' =>    2],
            ['ref' => 'SIL_200_172', 'designation' => '	SOI 135µm-1µm_725µm_725-375_OHMS	', ' stock' =>    22],
            ['ref' => 'SIL_300_101', 'designation' => '	VERRE_BOROFLOAT33_700UM_DSP_TTV<5UM	', ' stock' =>    93],
            ['ref' => 'SIL_300_102', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', ' stock' =>    1289],
            ['ref' => 'SIL_300_102Sas', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', ' stock' =>    100],
            ['ref' => 'SIL_300_102 STOCK RIVES', 'designation' => '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', ' stock' =>    2700],
            ['ref' => 'SIL_300_104', 'designation' => '	SILICIUM_P_EXTRA-PRIME_CZ_<100>_775UM_10-20OHM_DSP	', ' stock' =>    100],
            ['ref' => 'SIL_300_105 STOCK RIVES', 'designation' => '	SILICIUM_P_PRIME_CZ_<100>_775UM_10-20OHM_DSP	', ' stock' =>    450],
            ['ref' => 'SIL_300_105', 'designation' => '	SILICIUM_P_PRIME_CZ_<100>_775UM_10-20OHM_DSP	', ' stock' =>    126],
            ['ref' => 'SIL_300_106', 'designation' => '	SILICIUM_N_PRIME_CZ_<100>_775UM_20-60OHM_DSP	', ' stock' =>    253],
            ['ref' => 'SIL_300_106 STOCK RIVES', 'designation' => '	SILICIUM_N_PRIME_CZ_<100>_775UM_20-60OHM_DSP	', ' stock' =>    175],
            ['ref' => 'SIL_300_109', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', ' stock' =>    293],
            ['ref' => 'SIL_300_109Sas', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', ' stock' =>    50],
            ['ref' => 'SIL_300_109 STOCK RIVES', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', ' stock' =>    1525],
            ['ref' => 'SIL_300_110 STOCK RIVES', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_2_MINI-700UM	', ' stock' =>    500],
            ['ref' => 'SIL_300_110', 'designation' => '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_2_MINI-700UM	', ' stock' =>    242],
            ['ref' => 'SIL_300_117A', 'designation' => '	SOI_PRIME_12NM-25NM_P_CZ_<100>_775UM_10-15OHM_0°OF	', ' stock' =>    26],
            ['ref' => 'SIL_300_121', 'designation' => '	SI_SOI_PRIME_88NM-145NM_P_CZ_(100)_775µm_9-15OHM	', ' stock' =>    35],
            ['ref' => 'SIL_300_122 STOCK RIVES', 'designation' => '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM	', ' stock' =>    124],
            ['ref' => 'SIL_300_122', 'designation' => '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM	', ' stock' =>    25],
            ['ref' => 'SIL_300_128', 'designation' => '	SOI_PRIME_16NM-145NM_P_CZ_<100>_775UM_10-15OHM_0°', ' stock' =>        468],
            ['ref' => 'SIL_300_143 STOCK RIVES', 'designation' => '	300_143 RIVES	', ' stock' =>    50],
            ['ref' => 'SIL_300_143', 'designation' => '	300_143	', ' stock' =>    0],
            ['ref' => 'SIL_300_144', 'designation' => '	SOI_PRIME_12NM-145NM_P_CZ_<100>_775UM_10-15OHM	', ' stock' =>    3],
            ['ref' => 'SIL_300_147A', 'designation' => '	SILICIUM_P+_PRIME_FR_<100>_775UM_1,08-1,8OHM_DES4°	', ' stock' =>    136],
            ['ref' => 'SIL_300_148A', 'designation' => '	SOI_PRIM_14-25NM_P_<100>_775UM_0°OF_10-15OHM_Ra0.2	', ' stock' =>    52],
            ['ref' => 'SIL_300_149A', 'designation' => '	SOI_PRIME_310NM-720NM	', ' stock' =>    88],
            ['ref' => 'SIL_300_150', 'designation' => '	SOI_MON_14-25NM_P_<100>_775UM_0°OF_10-15OHM_Ra0.08	', ' stock' =>    1],
            ['ref' => 'SIL_300_151', 'designation' => '	CARRIER-ZONE-BOND_3MM	', ' stock' =>    70],
            ['ref' => 'SIL_300_152A', 'designation' => '	UTBOX15_PRO_PRIME_12-15NM_LOW-Ra', ' stock' =>        0],
            ['ref' => 'SIL_300_152A STOCK RIVES', 'designation' => '	UTBOX15_PRO_PRIME_12-15NM_LOW-Ra', ' stock' =>        100],
            ['ref' => 'SIL_300_153', 'designation' => '	SOI_MONITOR_14NM-20NM_10-15OHM	', ' stock' =>    3],
            ['ref' => 'SIL_300_155 STOCK RIVES', 'designation' => '	300_155 RIVES	', ' stock' =>    100],
            ['ref' => 'SIL_300_155', 'designation' => '	300_155	', ' stock' =>    75],
            ['ref' => 'SIL_300_157', 'designation' => '	SOI_P_<100>_10-15OHM 	', ' stock' =>    28],
            ['ref' => 'SIL_300_158A', 'designation' => '	SOI_PRIME_220NM-2000NM_P_CZ_<100>_775UM 	', ' stock' =>    59],
            ['ref' => 'SIL_300_159', 'designation' => '	VERRE_EAGLE-XG_700UM_DSP_TTV<5UM	', ' stock' =>    178],
            ['ref' => 'SIL_300_160A', 'designation' => '	SILICIUM P <100> 0.5° orientation ', ' stock' =>        61],
            ['ref' => 'SIL_300_162', 'designation' => '	SOI_PRIME_15-20NM_P_<100>_775µm_9-15OHMS	', ' stock' =>    53],
            ['ref' => 'SIL_300_167A', 'designation' => '	300MM-SOI-310-720NM	', ' stock' =>    25],
            ['ref' => 'SIL_300_168A', 'designation' => '	SIL MONITOR CZ(100)775µm 1-100OHM*CMSFQR 26*8MM ', ' stock' =>        100],
            ['ref' => 'SIL_100_009', 'designation' => '	100_P_BORON_FZ(100)525um_1-5_ohm', ' stock' =>        57],
            ['ref' => 'SIL_100_010', 'designation' => '	100_P_BORON_FZ(100)525um_1-5_ohm_off-0,15	', ' stock' =>    25],
            ['ref' => 'SIL_100_011', 'designation' => '	100_N_PH_FZ(100)525um_1-5_ohm', ' stock' =>    75],
            ['ref' => 'SIL_300_163A', 'designation' => 'na', ' stock' =>        0],
            ['ref' => 'SIL_300_165A', 'designation' => 'na', ' stock' =>        0],
            ['ref' => 'SIL_300_166', 'designation' => 'na', ' stock' =>        0],
            ['ref' => 'SIL_200_173', 'designation' => '	WAF.RAW 200 M M RESERV_SILICIUM_P_MONITOR_B_111_725UM_1-100_OHM_TTV<4UM	', ' stock' =>    175],
            ['ref' => 'SIL_300_174', 'designation' => '	300mm soi 70-145nm	', ' stock' =>    50],
            ['ref' => 'SIL_300_121A', 'designation' => '	300MM_SOI_PRIME_DECLASSE_88NM-145NM_P_CZ (100)_775UM_9-15 OHM	', ' stock' =>    100],


        ];
        foreach ($refArticleSiliS as $refArticleSili) {
            $referenceArticle = new ReferenceArticle();
            $referenceArticle
                ->setType($this->typeRepository->find(1))
                ->setReference($refArticleSili['ref'])
                ->setQuantiteStock($refArticleSili[' stock'])
                ->setLibelle($refArticleSili['designation']);
            $manager->persist($referenceArticle);
        }
        $manager->flush();
    }
}
