<?php
// formula.php — JAMB Formula Reference Panel
// Place at ~/excellent-academy/formula.php
error_reporting(E_ERROR|E_PARSE); ini_set('display_errors','0');
session_start();
require_once __DIR__.'/config/db.php';
$user_id=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
if(!$user_id){header('Location: login.html?redirect=formula.php');exit;}
$userRow=[];
$s=$conn->prepare("SELECT username,google_name,google_picture FROM users WHERE id=? LIMIT 1");
if($s){$s->bind_param('i',$user_id);$s->execute();$userRow=$s->get_result()->fetch_assoc()??[];$s->close();}
$dn=$_SESSION['google_name']??$userRow['google_name']??$userRow['username']??'Student';
$dp=$_SESSION['google_picture']??$userRow['google_picture']??null;

/* ══════════════════════════════════════════════════════
   JAMB FORMULA DATABASE
   level: 1=Foundation 2=Basic 3=Intermediate 4=Advanced 5=Expert
══════════════════════════════════════════════════════ */
$D=[
 'Mathematics'=>['emoji'=>'📐','color'=>'#3b82f6','topics'=>[
  ['name'=>'Percentages & Commercial Maths','tl'=>1,'formulas'=>[
   ['n'=>'Percentage','f'=>'\\%=\\dfrac{\\text{Part}}{\\text{Whole}}\\times100','d'=>'Express a part as a fraction of the whole × 100','v'=>'Part = portion, Whole = total amount','l'=>1],
   ['n'=>'Percentage Change','f'=>'\\%\\text{ Change}=\\dfrac{\\text{New}-\\text{Old}}{\\text{Old}}\\times100','d'=>'Percentage rise or fall between two values','v'=>'New = final value, Old = original value','l'=>1],
   ['n'=>'Simple Interest','f'=>'SI=\\dfrac{PRT}{100}','d'=>'Interest earned at a fixed rate, no compounding','v'=>'P = principal, R = rate per annum (%), T = time (years)','l'=>1],
   ['n'=>'Total Amount (SI)','f'=>'A=P\\!\\left(1+\\dfrac{RT}{100}\\right)','d'=>'Principal plus simple interest accumulated','v'=>'P = principal, R = rate %, T = years','l'=>1],
   ['n'=>'Compound Interest Amount','f'=>'A=P\\!\\left(1+\\dfrac{r}{100}\\right)^{\\!n}','d'=>'Value grows by the same rate each period','v'=>'P = principal, r = rate % per period, n = number of periods','l'=>2],
   ['n'=>'Profit %','f'=>'\\text{Profit}\\%=\\dfrac{SP-CP}{CP}\\times100','d'=>'Gain expressed as % of cost price','v'=>'SP = selling price, CP = cost price','l'=>1],
   ['n'=>'Loss %','f'=>'\\text{Loss}\\%=\\dfrac{CP-SP}{CP}\\times100','d'=>'Loss expressed as % of cost price','v'=>'SP = selling price, CP = cost price','l'=>1],
  ]],
  ['name'=>'Indices & Logarithms','tl'=>2,'formulas'=>[
   ['n'=>'Product of Indices','f'=>'a^m\\times a^n=a^{m+n}','d'=>'Multiply powers with the same base; add exponents','v'=>'a = base, m and n = exponents','l'=>1],
   ['n'=>'Quotient of Indices','f'=>'\\dfrac{a^m}{a^n}=a^{m-n}','d'=>'Divide powers with same base; subtract exponents','v'=>'a = base, m and n = exponents','l'=>1],
   ['n'=>'Power of a Power','f'=>'(a^m)^n=a^{mn}','d'=>'Raise a power to another power; multiply exponents','v'=>'a = base, m and n = exponents','l'=>1],
   ['n'=>'Negative Index','f'=>'a^{-n}=\\dfrac{1}{a^n}','d'=>'Negative exponent means the reciprocal','v'=>'a ≠ 0, n = positive integer','l'=>2],
   ['n'=>'Fractional Index','f'=>'a^{m/n}=\\sqrt[n]{a^m}','d'=>'Numerator is the power, denominator is the root','v'=>'a = base, m/n = rational exponent','l'=>2],
   ['n'=>'Log Product Rule','f'=>'\\log_a(xy)=\\log_a x+\\log_a y','d'=>'Log of a product = sum of logs','v'=>'a = base, x,y = positive numbers','l'=>2],
   ['n'=>'Log Quotient Rule','f'=>'\\log_a\\!\\left(\\dfrac{x}{y}\\right)=\\log_a x-\\log_a y','d'=>'Log of a quotient = difference of logs','v'=>'a = base, x,y = positive numbers','l'=>2],
   ['n'=>'Log Power Rule','f'=>'\\log_a(x^n)=n\\log_a x','d'=>'Bring the exponent down as a multiplier','v'=>'a = base, x = positive, n = any real number','l'=>2],
   ['n'=>'Change of Base','f'=>'\\log_a x=\\dfrac{\\log_b x}{\\log_b a}','d'=>'Convert from one logarithm base to another','v'=>'a,b = bases (positive ≠1), x = positive number','l'=>3],
   ['n'=>'Zero Index','f'=>'a^0=1,\\;a\\neq0','d'=>'Any non-zero number to the power zero equals 1','v'=>'a = any non-zero number','l'=>1],
  ]],
  ['name'=>'Surds','tl'=>2,'formulas'=>[
   ['n'=>'Product of Surds','f'=>'\\sqrt{a}\\times\\sqrt{b}=\\sqrt{ab}','d'=>'Multiply surds by multiplying under the radical','v'=>'a,b = positive real numbers','l'=>2],
   ['n'=>'Simplify Surd','f'=>'\\sqrt{a^2b}=a\\sqrt{b}','d'=>'Extract perfect-square factors from under the radical','v'=>'a = perfect square factor, b = remaining factor','l'=>2],
   ['n'=>'Rationalise Denominator','f'=>'\\dfrac{k}{\\sqrt{a}}=\\dfrac{k\\sqrt{a}}{a}','d'=>'Multiply numerator and denominator by the surd','v'=>'k = any number, a = radicand','l'=>2],
   ['n'=>'Conjugate Rationalisation','f'=>'\\dfrac{1}{a+\\sqrt{b}}=\\dfrac{a-\\sqrt{b}}{a^2-b}','d'=>'Use conjugate pair to eliminate surd in denominator','v'=>'a = rational number, b = radicand','l'=>3],
  ]],
  ['name'=>'Algebra — Equations','tl'=>2,'formulas'=>[
   ['n'=>'Difference of Two Squares','f'=>'a^2-b^2=(a+b)(a-b)','d'=>'Factorise any expression of the form a² − b²','v'=>'a,b = any algebraic terms','l'=>1],
   ['n'=>'Square of a Sum','f'=>'(a+b)^2=a^2+2ab+b^2','d'=>'Expand the square of two terms added','v'=>'a,b = algebraic terms','l'=>1],
   ['n'=>'Square of a Difference','f'=>'(a-b)^2=a^2-2ab+b^2','d'=>'Expand the square of two terms subtracted','v'=>'a,b = algebraic terms','l'=>1],
   ['n'=>'Quadratic Formula','f'=>'x=\\dfrac{-b\\pm\\sqrt{b^2-4ac}}{2a}','d'=>'Solve any quadratic ax² + bx + c = 0','v'=>'a,b,c = coefficients of quadratic; a ≠ 0','l'=>2],
   ['n'=>'Discriminant','f'=>'\\Delta=b^2-4ac','d'=>'Δ > 0: two real roots; Δ = 0: one root; Δ < 0: no real roots','v'=>'a,b,c = coefficients of ax² + bx + c','l'=>2],
   ['n'=>'Sum of Roots','f'=>'\\alpha+\\beta=-\\dfrac{b}{a}','d'=>'Sum of the two roots of a quadratic','v'=>'α,β = roots; a,b = coefficients','l'=>2],
   ['n'=>'Product of Roots','f'=>'\\alpha\\beta=\\dfrac{c}{a}','d'=>'Product of the two roots of a quadratic','v'=>'α,β = roots; a,c = coefficients','l'=>2],
   ['n'=>'Direct Variation','f'=>'y\\propto x\\Rightarrow y=kx','d'=>'y and x increase or decrease together proportionally','v'=>'k = constant of proportionality','l'=>2],
   ['n'=>'Inverse Variation','f'=>'y\\propto\\dfrac{1}{x}\\Rightarrow y=\\dfrac{k}{x}','d'=>'y decreases as x increases','v'=>'k = constant of proportionality; x ≠ 0','l'=>2],
  ]],
  ['name'=>'Sequences & Series','tl'=>3,'formulas'=>[
   ['n'=>'AP — nth Term','f'=>'T_n=a+(n-1)d','d'=>'Any term of an Arithmetic Progression','v'=>'a = first term, d = common difference, n = term position','l'=>2],
   ['n'=>'AP — Sum of n Terms','f'=>'S_n=\\dfrac{n}{2}[2a+(n-1)d]','d'=>'Sum of first n terms of an AP','v'=>'a = first term, d = common difference, n = number of terms','l'=>2],
   ['n'=>'AP — Sum (first and last)','f'=>'S_n=\\dfrac{n}{2}(a+l)','d'=>'Sum when first and last term are known','v'=>'a = first term, l = last term, n = number of terms','l'=>2],
   ['n'=>'GP — nth Term','f'=>'T_n=ar^{n-1}','d'=>'Any term of a Geometric Progression','v'=>'a = first term, r = common ratio, n = term position','l'=>2],
   ['n'=>'GP — Sum of n Terms','f'=>'S_n=\\dfrac{a(r^n-1)}{r-1},\\;r\\neq1','d'=>'Sum of first n terms of a GP','v'=>'a = first term, r = common ratio (r ≠ 1)','l'=>3],
   ['n'=>'GP — Sum to Infinity','f'=>'S_\\infty=\\dfrac{a}{1-r},\\;|r|<1','d'=>'Sum of an infinite GP with ratio less than 1','v'=>'a = first term, r = common ratio; must have |r| < 1','l'=>3],
  ]],
  ['name'=>'Coordinate Geometry','tl'=>3,'formulas'=>[
   ['n'=>'Distance Formula','f'=>'d=\\sqrt{(x_2-x_1)^2+(y_2-y_1)^2}','d'=>'Length of a segment between two coordinate points','v'=>'(x₁,y₁) and (x₂,y₂) = coordinate pairs','l'=>2],
   ['n'=>'Midpoint Formula','f'=>'M=\\left(\\dfrac{x_1+x_2}{2},\\dfrac{y_1+y_2}{2}\\right)','d'=>'Point exactly halfway between two given points','v'=>'(x₁,y₁) and (x₂,y₂) = endpoints','l'=>2],
   ['n'=>'Gradient (Slope)','f'=>'m=\\dfrac{y_2-y_1}{x_2-x_1}','d'=>'Steepness of a straight line','v'=>'(x₁,y₁) and (x₂,y₂) = any two distinct points on the line','l'=>2],
   ['n'=>'Equation of a Line (y=mx+c)','f'=>'y=mx+c','d'=>'Slope-intercept form of a straight line','v'=>'m = gradient, c = y-intercept','l'=>2],
   ['n'=>'Equation of a Line (point-slope)','f'=>'y-y_1=m(x-x_1)','d'=>'Line through point (x₁,y₁) with gradient m','v'=>'m = gradient, (x₁,y₁) = known point on line','l'=>2],
   ['n'=>'Circle Equation','f'=>'(x-a)^2+(y-b)^2=r^2','d'=>'Circle with centre (a,b) and radius r','v'=>'(a,b) = centre coordinates, r = radius','l'=>3],
   ['n'=>'Perpendicular Lines','f'=>'m_1\\times m_2=-1','d'=>'Gradients of perpendicular lines multiply to −1','v'=>'m₁,m₂ = gradients of the two lines','l'=>3],
  ]],
  ['name'=>'Trigonometry','tl'=>3,'formulas'=>[
   ['n'=>'Sine (SOH)','f'=>'\\sin\\theta=\\dfrac{\\text{opp}}{\\text{hyp}}','d'=>'Basic definition: opposite over hypotenuse','v'=>'θ = angle, opp = opposite side, hyp = hypotenuse','l'=>1],
   ['n'=>'Cosine (CAH)','f'=>'\\cos\\theta=\\dfrac{\\text{adj}}{\\text{hyp}}','d'=>'Basic definition: adjacent over hypotenuse','v'=>'θ = angle, adj = adjacent side, hyp = hypotenuse','l'=>1],
   ['n'=>'Tangent (TOA)','f'=>'\\tan\\theta=\\dfrac{\\text{opp}}{\\text{adj}}=\\dfrac{\\sin\\theta}{\\cos\\theta}','d'=>'Basic definition: opposite over adjacent','v'=>'θ = angle, opp = opposite, adj = adjacent','l'=>1],
   ['n'=>'Pythagorean Identity','f'=>'\\sin^2\\theta+\\cos^2\\theta=1','d'=>'Fundamental identity — holds for all angles','v'=>'θ = any angle','l'=>2],
   ['n'=>'Tangent Identity','f'=>'1+\\tan^2\\theta=\\sec^2\\theta','d'=>'Pythagorean identity in terms of tangent','v'=>'θ = any angle (≠ 90° + n·180°)','l'=>3],
   ['n'=>'Cotangent Identity','f'=>'1+\\cot^2\\theta=\\csc^2\\theta','d'=>'Pythagorean identity in terms of cotangent','v'=>'θ = any angle (≠ 0° + n·180°)','l'=>3],
   ['n'=>'Sine Addition Formula','f'=>'\\sin(A\\pm B)=\\sin A\\cos B\\pm\\cos A\\sin B','d'=>'Sine of sum or difference of two angles','v'=>'A,B = any two angles','l'=>4],
   ['n'=>'Cosine Addition Formula','f'=>'\\cos(A\\pm B)=\\cos A\\cos B\\mp\\sin A\\sin B','d'=>'Cosine of sum or difference of two angles','v'=>'A,B = any two angles','l'=>4],
   ['n'=>'Double Angle — Sine','f'=>'\\sin 2A=2\\sin A\\cos A','d'=>'Sine of a doubled angle','v'=>'A = any angle','l'=>4],
   ['n'=>'Double Angle — Cosine','f'=>'\\cos 2A=\\cos^2\\!A-\\sin^2\\!A=1-2\\sin^2\\!A','d'=>'Cosine of a doubled angle (two useful forms)','v'=>'A = any angle','l'=>4],
   ['n'=>'Sine Rule','f'=>'\\dfrac{a}{\\sin A}=\\dfrac{b}{\\sin B}=\\dfrac{c}{\\sin C}','d'=>'Relates sides and opposite angles in any triangle','v'=>'a,b,c = sides; A,B,C = opposite angles','l'=>3],
   ['n'=>'Cosine Rule','f'=>'a^2=b^2+c^2-2bc\\cos A','d'=>'Finds a side or angle in any triangle','v'=>'a = unknown side; b,c = known sides; A = included angle','l'=>3],
   ['n'=>'Area (trig)','f'=>'\\text{Area}=\\tfrac{1}{2}ab\\sin C','d'=>'Area of a triangle using two sides and included angle','v'=>'a,b = two sides; C = angle between them','l'=>3],
  ]],
  ['name'=>'Mensuration','tl'=>2,'formulas'=>[
   ['n'=>'Area — Rectangle','f'=>'A=l\\times w','d'=>'Area of a rectangle','v'=>'l = length, w = width','l'=>1],
   ['n'=>'Area — Triangle','f'=>'A=\\tfrac{1}{2}\\times b\\times h','d'=>'Area of any triangle','v'=>'b = base length, h = perpendicular height','l'=>1],
   ['n'=>'Area — Trapezium','f'=>'A=\\tfrac{1}{2}(a+b)h','d'=>'Area of a trapezium with two parallel sides','v'=>'a,b = parallel sides, h = perpendicular height','l'=>2],
   ['n'=>'Area — Circle','f'=>'A=\\pi r^2','d'=>'Area enclosed within a circle','v'=>'r = radius, π ≈ 3.14159','l'=>1],
   ['n'=>'Circumference','f'=>'C=2\\pi r=\\pi d','d'=>'Perimeter of a circle','v'=>'r = radius, d = diameter','l'=>1],
   ['n'=>'Arc Length','f'=>'L=\\dfrac{\\theta}{360}\\times2\\pi r','d'=>'Length of an arc subtending angle θ°','v'=>'θ = angle in degrees, r = radius','l'=>2],
   ['n'=>'Area — Sector','f'=>'A=\\dfrac{\\theta}{360}\\times\\pi r^2','d'=>'Area of a pie-slice sector of a circle','v'=>'θ = angle in degrees, r = radius','l'=>2],
   ['n'=>'Volume — Cylinder','f'=>'V=\\pi r^2 h','d'=>'Volume of a right circular cylinder','v'=>'r = radius, h = height','l'=>2],
   ['n'=>'Volume — Cone','f'=>'V=\\tfrac{1}{3}\\pi r^2 h','d'=>'Volume of a right circular cone','v'=>'r = base radius, h = perpendicular height','l'=>2],
   ['n'=>'Volume — Sphere','f'=>'V=\\tfrac{4}{3}\\pi r^3','d'=>'Volume enclosed within a sphere','v'=>'r = radius','l'=>2],
   ['n'=>'Surface Area — Sphere','f'=>'SA=4\\pi r^2','d'=>'Total outer surface area of a sphere','v'=>'r = radius','l'=>2],
   ['n'=>'Surface Area — Cylinder (total)','f'=>'SA=2\\pi r(r+h)','d'=>'Two circular ends plus the curved lateral surface','v'=>'r = radius, h = height','l'=>2],
   ['n'=>'Curved Surface — Cone','f'=>'CSA=\\pi r l','d'=>'Slanted outer surface of a cone (no base)','v'=>'r = base radius, l = slant height','l'=>3],
   ['n'=>'Pythagoras Theorem','f'=>'c^2=a^2+b^2','d'=>'Relates sides of a right-angled triangle','v'=>'c = hypotenuse, a,b = other two sides','l'=>1],
  ]],
  ['name'=>'Statistics & Probability','tl'=>2,'formulas'=>[
   ['n'=>'Arithmetic Mean','f'=>'\\bar{x}=\\dfrac{\\sum x}{n}','d'=>'Sum of all values divided by the count','v'=>'Σx = sum of values, n = number of values','l'=>1],
   ['n'=>'Mean (Frequency Table)','f'=>'\\bar{x}=\\dfrac{\\sum fx}{\\sum f}','d'=>'Weighted mean from a frequency distribution','v'=>'f = frequency, x = class midpoint or value','l'=>2],
   ['n'=>'Variance','f'=>'\\sigma^2=\\dfrac{\\sum(x-\\bar{x})^2}{n}','d'=>'Average of squared deviations from the mean','v'=>'x = each value, x̄ = mean, n = count','l'=>3],
   ['n'=>'Standard Deviation','f'=>'\\sigma=\\sqrt{\\dfrac{\\sum(x-\\bar{x})^2}{n}}','d'=>'Measure of spread; square root of the variance','v'=>'x = each value, x̄ = mean, n = count','l'=>3],
   ['n'=>'Probability','f'=>'P(A)=\\dfrac{n(A)}{n(S)}','d'=>'Ratio of favourable to total equally-likely outcomes','v'=>'n(A) = favourable outcomes, n(S) = sample space size','l'=>1],
   ['n'=>'Complement','f'=>"P(A')=1-P(A)",'d'=>'Probability that A does NOT occur','v'=>"A' = complement of A",'l'=>2],
   ['n'=>'Addition Rule (mutually exclusive)','f'=>'P(A\\cup B)=P(A)+P(B)','d'=>'A and B cannot happen at the same time','v'=>'A,B = mutually exclusive events','l'=>2],
   ['n'=>'Addition Rule (general)','f'=>'P(A\\cup B)=P(A)+P(B)-P(A\\cap B)','d'=>'General rule for any two events','v'=>'P(A∩B) = probability both occur simultaneously','l'=>3],
   ['n'=>'Multiplication Rule (independent)','f'=>'P(A\\cap B)=P(A)\\times P(B)','d'=>'Both independent events occur together','v'=>'A,B = independent events','l'=>2],
   ['n'=>'Permutations nPr','f'=>'^nP_r=\\dfrac{n!}{(n-r)!}','d'=>'Arrangements of r items from n (order matters)','v'=>'n = total items, r = chosen items','l'=>3],
   ['n'=>'Combinations nCr','f'=>'^nC_r=\\dfrac{n!}{r!(n-r)!}','d'=>'Selections of r items from n (order does not matter)','v'=>'n = total items, r = chosen items','l'=>3],
  ]],
  ['name'=>'Differentiation','tl'=>4,'formulas'=>[
   ['n'=>'Power Rule','f'=>'\\dfrac{d}{dx}(x^n)=nx^{n-1}','d'=>'Differentiate any power of x','v'=>'n = any real exponent','l'=>3],
   ['n'=>'Constant Rule','f'=>'\\dfrac{d}{dx}(k)=0','d'=>'Derivative of a constant is zero','v'=>'k = any constant','l'=>3],
   ['n'=>'Sum/Difference Rule','f'=>'\\dfrac{d}{dx}[f\\pm g]=f\'\\pm g\'','d'=>'Differentiate each term separately','v'=>"f,g = differentiable functions; f',g' = their derivatives",'l'=>3],
   ['n'=>'Product Rule','f'=>'\\dfrac{d}{dx}[uv]=u\\,v\'+v\\,u\'','d'=>'Differentiate a product of two functions','v'=>"u,v = functions of x; u',v' = their derivatives",'l'=>4],
   ['n'=>'Chain Rule','f'=>'\\dfrac{dy}{dx}=\\dfrac{dy}{du}\\cdot\\dfrac{du}{dx}','d'=>'Differentiate a composite (function of a function)','v'=>'y = f(u), u = g(x)','l'=>4],
   ['n'=>'Stationary Point Condition','f'=>'f\'(x)=0','d'=>'Set derivative to zero to find max/min/inflection','v'=>"f'(x) = derivative; check f''(x) for nature",'l'=>4],
  ]],
  ['name'=>'Integration','tl'=>5,'formulas'=>[
   ['n'=>'Power Rule (Integration)','f'=>'\\int x^n\\,dx=\\dfrac{x^{n+1}}{n+1}+C,\\;n\\neq-1','d'=>'Integrate a power of x (reverse of power differentiation)','v'=>'n = exponent (≠−1), C = constant of integration','l'=>4],
   ['n'=>'Integral of Constant','f'=>'\\int k\\,dx=kx+C','d'=>'Integrate a constant','v'=>'k = constant, C = constant of integration','l'=>3],
   ['n'=>'Definite Integral','f'=>'\\int_a^b f(x)\\,dx=F(b)-F(a)','d'=>'Area under f(x) between x = a and x = b','v'=>'F(x) = antiderivative of f(x)','l'=>4],
   ['n'=>'Area Under Curve','f'=>'A=\\int_a^b f(x)\\,dx','d'=>'Exact area bounded by curve, x-axis and the two vertical lines','v'=>'f(x) ≥ 0 on [a,b]; a,b = limits of integration','l'=>5],
  ]],
 ]],

 'Physics'=>['emoji'=>'⚡','color'=>'#f59e0b','topics'=>[
  ['name'=>'Measurement & Units','tl'=>1,'formulas'=>[
   ['n'=>'Density','f'=>'\\rho=\\dfrac{m}{V}','d'=>'Mass per unit volume of a substance','v'=>'m = mass (kg), V = volume (m³), ρ = density (kg/m³)','l'=>1],
   ['n'=>'Relative Density','f'=>'RD=\\dfrac{\\text{density of substance}}{\\text{density of water}}','d'=>'Dimensionless comparison; water has RD = 1','v'=>'Density of water = 1000 kg/m³ or 1 g/cm³','l'=>1],
  ]],
  ['name'=>'Kinematics (Linear Motion)','tl'=>2,'formulas'=>[
   ['n'=>'Speed','f'=>'v=\\dfrac{d}{t}','d'=>'Distance travelled per unit time','v'=>'d = distance (m), t = time (s)','l'=>1],
   ['n'=>'Average Velocity','f'=>'\\bar{v}=\\dfrac{u+v}{2}','d'=>'Mean velocity under constant acceleration','v'=>'u = initial velocity, v = final velocity','l'=>1],
   ['n'=>'1st Equation of Motion','f'=>'v=u+at','d'=>'Final velocity from initial velocity and acceleration','v'=>'u = initial, v = final velocity, a = acceleration, t = time','l'=>1],
   ['n'=>'2nd Equation of Motion','f'=>'s=ut+\\tfrac{1}{2}at^2','d'=>'Displacement covered in time t','v'=>'s = displacement, u = initial velocity, a = acceleration, t = time','l'=>2],
   ['n'=>'3rd Equation of Motion','f'=>'v^2=u^2+2as','d'=>'Velocity after covering displacement s','v'=>'v = final, u = initial velocity, a = acceleration, s = displacement','l'=>2],
   ['n'=>'Acceleration','f'=>'a=\\dfrac{v-u}{t}','d'=>'Rate of change of velocity','v'=>'v = final, u = initial velocity, t = time','l'=>1],
   ['n'=>'Projectile Range','f'=>'R=\\dfrac{u^2\\sin2\\theta}{g}','d'=>'Horizontal distance covered by a projectile on level ground','v'=>'u = launch speed, θ = launch angle, g = 9.8 m/s²','l'=>4],
   ['n'=>'Maximum Height (projectile)','f'=>'H=\\dfrac{u^2\\sin^2\\theta}{2g}','d'=>'Greatest height reached by projectile','v'=>'u = launch speed, θ = angle, g = 9.8 m/s²','l'=>4],
   ['n'=>'Time of Flight (projectile)','f'=>'T=\\dfrac{2u\\sin\\theta}{g}','d'=>'Total time the projectile is airborne','v'=>'u = launch speed, θ = launch angle, g = 9.8 m/s²','l'=>3],
  ]],
  ['name'=>'Forces & Newton\'s Laws','tl'=>2,'formulas'=>[
   ['n'=>'Newton\'s Second Law','f'=>'F=ma','d'=>'Net force equals mass times acceleration','v'=>'F = net force (N), m = mass (kg), a = acceleration (m/s²)','l'=>1],
   ['n'=>'Weight','f'=>'W=mg','d'=>'Gravitational pull on an object','v'=>'m = mass (kg), g = 9.8 m/s² (or 10 m/s² approx.)','l'=>1],
   ['n'=>'Friction','f'=>'F_f=\\mu R','d'=>'Maximum static or kinetic frictional force','v'=>'μ = coefficient of friction, R = normal reaction force (N)','l'=>2],
   ['n'=>'Moment of a Force','f'=>'M=F\\times d','d'=>'Turning effect of a force about a pivot','v'=>'F = force (N), d = perpendicular distance from pivot (m)','l'=>2],
   ['n'=>'Pressure','f'=>'P=\\dfrac{F}{A}','d'=>'Normal force per unit area','v'=>'F = force (N), A = area (m²), P = pressure (Pa)','l'=>1],
   ['n'=>'Fluid Pressure','f'=>'P=\\rho g h','d'=>'Pressure at depth h in a fluid','v'=>'ρ = density (kg/m³), g = 9.8 m/s², h = depth (m)','l'=>3],
   ['n'=>'Upthrust (Archimedes)','f'=>'F_b=\\rho_f g V','d'=>'Upward buoyant force equals weight of fluid displaced','v'=>'ρ_f = fluid density, g = 9.8 m/s², V = submerged volume (m³)','l'=>3],
  ]],
  ['name'=>'Work, Energy & Power','tl'=>2,'formulas'=>[
   ['n'=>'Work Done','f'=>'W=Fd\\cos\\theta','d'=>'Work by a force over a displacement','v'=>'F = force (N), d = displacement (m), θ = angle between force and displacement','l'=>2],
   ['n'=>'Kinetic Energy','f'=>'KE=\\tfrac{1}{2}mv^2','d'=>'Energy due to motion','v'=>'m = mass (kg), v = speed (m/s)','l'=>1],
   ['n'=>'Gravitational PE','f'=>'PE=mgh','d'=>'Energy stored due to height in a gravity field','v'=>'m = mass, g = 9.8 m/s², h = height (m)','l'=>1],
   ['n'=>'Power','f'=>'P=\\dfrac{W}{t}=Fv','d'=>'Rate of doing work','v'=>'W = work (J), t = time (s), F = force, v = velocity','l'=>1],
   ['n'=>'Efficiency','f'=>'\\eta=\\dfrac{P_{out}}{P_{in}}\\times100\\%','d'=>'Ratio of useful output to total input (as %)','v'=>'P_out = useful power out, P_in = total power in','l'=>2],
   ['n'=>'Speed after Free Fall','f'=>'v=\\sqrt{2gh}','d'=>'Speed gained by an object falling from rest through height h','v'=>'g = 9.8 m/s², h = height of fall (m)','l'=>2],
  ]],
  ['name'=>'Momentum & Collisions','tl'=>3,'formulas'=>[
   ['n'=>'Linear Momentum','f'=>'p=mv','d'=>'Product of mass and velocity; a vector quantity','v'=>'m = mass (kg), v = velocity (m/s)','l'=>1],
   ['n'=>'Impulse','f'=>'J=F\\Delta t=\\Delta p','d'=>'Change in momentum equals the impulse applied','v'=>'F = force, Δt = time interval, Δp = change in momentum','l'=>2],
   ['n'=>'Conservation of Momentum','f'=>'m_1u_1+m_2u_2=m_1v_1+m_2v_2','d'=>'Total momentum is conserved when no external force acts','v'=>'m = masses; u = initial, v = final velocities','l'=>2],
  ]],
  ['name'=>'Waves & Sound','tl'=>3,'formulas'=>[
   ['n'=>'Wave Speed','f'=>'v=f\\lambda','d'=>'Speed equals frequency times wavelength','v'=>'v = wave speed (m/s), f = frequency (Hz), λ = wavelength (m)','l'=>1],
   ['n'=>'Period','f'=>'T=\\dfrac{1}{f}','d'=>'Time for one complete oscillation','v'=>'T = period (s), f = frequency (Hz)','l'=>1],
   ['n'=>'Frequency','f'=>'f=\\dfrac{1}{T}','d'=>'Number of complete oscillations per second','v'=>'f = frequency (Hz), T = period (s)','l'=>1],
   ['n'=>'Snell\'s Law','f'=>'n_1\\sin\\theta_1=n_2\\sin\\theta_2','d'=>'Refraction law at a boundary between two media','v'=>'n₁,n₂ = refractive indices; θ₁,θ₂ = angles to the normal','l'=>3],
   ['n'=>'Refractive Index','f'=>'n=\\dfrac{\\sin i}{\\sin r}=\\dfrac{c}{v_m}','d'=>'How much a medium bends light compared to air','v'=>'i = angle of incidence, r = angle of refraction, c = 3×10⁸ m/s, v_m = speed in medium','l'=>2],
   ['n'=>'Critical Angle','f'=>'\\sin C=\\dfrac{1}{n}','d'=>'Angle of incidence for total internal reflection in denser medium','v'=>'C = critical angle, n = refractive index of denser medium','l'=>3],
  ]],
  ['name'=>'Optics — Mirrors & Lenses','tl'=>3,'formulas'=>[
   ['n'=>'Mirror / Lens Formula','f'=>'\\dfrac{1}{f}=\\dfrac{1}{u}+\\dfrac{1}{v}','d'=>'Relates focal length, object and image distances','v'=>'f = focal length, u = object distance, v = image distance (sign convention applies)','l'=>3],
   ['n'=>'Magnification','f'=>'m=\\dfrac{v}{u}=\\dfrac{h_i}{h_o}','d'=>'Ratio of image size to object size','v'=>'v = image dist, u = object dist, h_i = image height, h_o = object height','l'=>3],
   ['n'=>'Lens Power','f'=>'P=\\dfrac{1}{f}\\;\\text{(dioptres, }f\\text{ in metres)}','d'=>'Converging lens: positive power; diverging: negative','v'=>'f = focal length in metres','l'=>3],
  ]],
  ['name'=>'Heat & Thermodynamics','tl'=>3,'formulas'=>[
   ['n'=>'Heat Equation','f'=>'Q=mc\\Delta\\theta','d'=>'Heat absorbed or released to change temperature','v'=>'m = mass (kg), c = specific heat capacity (J/kg·°C), Δθ = temperature change','l'=>2],
   ['n'=>'Latent Heat','f'=>'Q=mL','d'=>'Heat for a change of state at constant temperature','v'=>'m = mass (kg), L = specific latent heat (J/kg)','l'=>2],
   ['n'=>'Temperature Conversion','f'=>'T\\,(\\text{K})=T\\,(°\\text{C})+273','d'=>'Convert Celsius to Kelvin (absolute temperature)','v'=>'K = Kelvin, °C = Celsius','l'=>1],
   ['n'=>'Boyle\'s Law','f'=>'P_1V_1=P_2V_2\\;(T\\text{ const.})','d'=>'Pressure and volume are inversely proportional at constant temperature','v'=>'P = pressure, V = volume; temperature T must be fixed','l'=>2],
   ['n'=>'Charles\' Law','f'=>'\\dfrac{V_1}{T_1}=\\dfrac{V_2}{T_2}\\;(P\\text{ const.})','d'=>'Volume is directly proportional to absolute temperature','v'=>'V = volume, T = temperature in Kelvin (not Celsius!)','l'=>2],
   ['n'=>'General Gas Law','f'=>'\\dfrac{P_1V_1}{T_1}=\\dfrac{P_2V_2}{T_2}','d'=>'Combines Boyle\'s and Charles\' laws','v'=>'P = pressure, V = volume, T = absolute temperature (K)','l'=>2],
   ['n'=>'Ideal Gas Equation','f'=>'PV=nRT','d'=>'Equation of state for an ideal gas','v'=>'P = pressure (Pa), V = volume (m³), n = moles, R = 8.314 J/mol·K, T = K','l'=>3],
  ]],
  ['name'=>'Electricity & Circuits','tl'=>3,'formulas'=>[
   ['n'=>'Ohm\'s Law','f'=>'V=IR','d'=>'Voltage = current × resistance','v'=>'V = voltage (V), I = current (A), R = resistance (Ω)','l'=>1],
   ['n'=>'Electric Power','f'=>'P=IV=I^2R=\\dfrac{V^2}{R}','d'=>'Three equivalent forms of electrical power','v'=>'P = power (W), I = current (A), V = voltage (V), R = resistance (Ω)','l'=>2],
   ['n'=>'Electrical Energy','f'=>'E=Pt=VIt','d'=>'Energy consumed by an electrical device over time','v'=>'P = power (W), t = time (s), V = voltage, I = current','l'=>2],
   ['n'=>'Charge','f'=>'Q=It','d'=>'Charge is the product of current and time','v'=>'Q = charge (coulombs), I = current (A), t = time (s)','l'=>1],
   ['n'=>'Series Resistance','f'=>'R_T=R_1+R_2+R_3+\\cdots','d'=>'Add all resistances in series','v'=>'R₁,R₂,R₃ = individual resistance values (Ω)','l'=>2],
   ['n'=>'Parallel Resistance','f'=>'\\dfrac{1}{R_T}=\\dfrac{1}{R_1}+\\dfrac{1}{R_2}+\\cdots','d'=>'Reciprocal of total equals sum of reciprocals','v'=>'R₁,R₂ = individual resistance values (Ω)','l'=>2],
   ['n'=>'EMF of a Cell','f'=>'\\varepsilon=V+Ir','d'=>'EMF = terminal voltage + voltage lost internally','v'=>'ε = EMF (V), V = terminal p.d., I = current, r = internal resistance','l'=>3],
  ]],
  ['name'=>'Simple Harmonic Motion','tl'=>4,'formulas'=>[
   ['n'=>'Period — Simple Pendulum','f'=>'T=2\\pi\\sqrt{\\dfrac{L}{g}}','d'=>'Period of one full swing of a simple pendulum','v'=>'L = length (m), g = 9.8 m/s²','l'=>3],
   ['n'=>'Period — Mass-Spring','f'=>'T=2\\pi\\sqrt{\\dfrac{m}{k}}','d'=>'Period of a mass oscillating on a spring','v'=>'m = mass (kg), k = spring constant (N/m)','l'=>4],
   ['n'=>'Hooke\'s Law','f'=>'F=ke','d'=>'Force to extend or compress a spring','v'=>'k = spring constant (N/m), e = extension or compression (m)','l'=>2],
  ]],
  ['name'=>'Modern Physics','tl'=>5,'formulas'=>[
   ['n'=>'Photon Energy','f'=>'E=hf=\\dfrac{hc}{\\lambda}','d'=>'Energy of a single photon of electromagnetic radiation','v'=>'h = 6.626×10⁻³⁴ J·s, f = frequency, c = 3×10⁸ m/s, λ = wavelength','l'=>4],
   ['n'=>'Einstein Mass-Energy','f'=>'E=mc^2','d'=>'Mass and energy are interconvertible','v'=>'m = mass (kg), c = 3×10⁸ m/s','l'=>3],
   ['n'=>'Radioactive Decay','f'=>'N=N_0\\!\\left(\\dfrac{1}{2}\\right)^{\\!n}','d'=>'Number of undecayed atoms after n half-lives','v'=>'N₀ = initial atoms, n = number of half-lives elapsed','l'=>4],
  ]],
 ]],

 'Chemistry'=>['emoji'=>'⚗️','color'=>'#00c98a','topics'=>[
  ['name'=>'Atomic Structure','tl'=>1,'formulas'=>[
   ['n'=>'Mass Number','f'=>'A=Z+N','d'=>'Total nucleons = protons + neutrons','v'=>'A = mass number, Z = proton number (atomic number), N = neutron number','l'=>1],
   ['n'=>'Neutron Number','f'=>'N=A-Z','d'=>'Subtract proton number from mass number','v'=>'A = mass number, Z = atomic (proton) number','l'=>1],
   ['n'=>'Relative Atomic Mass (isotopes)','f'=>'A_r=\\sum\\!\\left(\\dfrac{\\text{abundance}}{100}\\times\\text{mass}\\right)','d'=>'Weighted average of all isotopic masses','v'=>'abundance = % natural abundance, mass = exact isotopic mass','l'=>2],
   ['n'=>'Maximum Electrons per Shell','f'=>'\\text{Max electrons in shell }n=2n^2','d'=>'Shell 1: max 2; Shell 2: max 8; Shell 3: max 18','v'=>'n = principal quantum number (1, 2, 3…)','l'=>2],
  ]],
  ['name'=>'Mole Concept & Stoichiometry','tl'=>2,'formulas'=>[
   ['n'=>'Moles from Mass','f'=>'n=\\dfrac{m}{M}','d'=>'Convert mass of a substance to amount in moles','v'=>'n = moles (mol), m = mass (g), M = molar mass (g/mol)','l'=>1],
   ['n'=>'Mass from Moles','f'=>'m=n\\times M','d'=>'Mass of a substance from its moles and molar mass','v'=>'n = moles, M = molar mass (g/mol)','l'=>1],
   ['n'=>'Moles of a Gas at STP','f'=>'n=\\dfrac{V}{22400}\\;(V\\text{ in cm}^3)','d'=>'One mole of any gas occupies 22,400 cm³ at STP','v'=>'V = volume (cm³), 22,400 = molar volume at STP','l'=>1],
   ['n'=>'Number of Particles','f'=>'N=n\\times N_A,\\;N_A=6.02\\times10^{23}','d'=>'Total atoms/molecules from moles','v'=>'n = moles, N_A = Avogadro\'s constant','l'=>2],
   ['n'=>'Concentration','f'=>'C=\\dfrac{n}{V}','d'=>'Amount of solute per litre of solution','v'=>'C = concentration (mol/dm³), n = moles, V = volume (dm³)','l'=>1],
   ['n'=>'Moles from Concentration','f'=>'n=C\\times V','d'=>'Moles = concentration × volume (in dm³)','v'=>'C = mol/dm³, V = dm³ (= litres)','l'=>1],
   ['n'=>'Percentage Yield','f'=>'\\%\\text{ yield}=\\dfrac{\\text{actual}}{\\text{theoretical}}\\times100','d'=>'How much product was actually obtained vs expected','v'=>'Actual and theoretical yields in the same units','l'=>2],
   ['n'=>'Percentage Purity','f'=>'\\%\\text{ purity}=\\dfrac{\\text{pure mass}}{\\text{sample mass}}\\times100','d'=>'Purity of a sample containing impurities','v'=>'Pure mass = mass of actual pure substance in the sample','l'=>2],
  ]],
  ['name'=>'Gas Laws','tl'=>2,'formulas'=>[
   ['n'=>'Boyle\'s Law','f'=>'P_1V_1=P_2V_2\\;(T\\text{ const.})','d'=>'Pressure and volume are inversely proportional at fixed temperature','v'=>'P = pressure, V = volume; temperature must stay constant','l'=>2],
   ['n'=>'Charles\' Law','f'=>'\\dfrac{V_1}{T_1}=\\dfrac{V_2}{T_2}\\;(P\\text{ const.})','d'=>'Volume is proportional to absolute temperature at fixed pressure','v'=>'V = volume, T = temperature in Kelvin (not Celsius)','l'=>2],
   ['n'=>'General Gas Equation','f'=>'\\dfrac{P_1V_1}{T_1}=\\dfrac{P_2V_2}{T_2}','d'=>'Combines Boyle\'s and Charles\' laws for a fixed mass of gas','v'=>'P = pressure, V = volume, T = temperature (K)','l'=>2],
   ['n'=>'Ideal Gas Law','f'=>'PV=nRT,\\;R=8.314\\,\\text{J mol}^{-1}\\text{K}^{-1}','d'=>'Equation of state for one mole or more of ideal gas','v'=>'P = pressure (Pa), V = m³, n = moles, R = 8.314, T = K','l'=>3],
   ['n'=>'Dalton\'s Law','f'=>'P_{\\text{total}}=P_1+P_2+P_3+\\cdots','d'=>'Total pressure is the sum of all partial pressures','v'=>'P₁,P₂,P₃ = partial pressures of each gas in mixture','l'=>3],
   ['n'=>'Graham\'s Law of Diffusion','f'=>'\\dfrac{r_1}{r_2}=\\sqrt{\\dfrac{M_2}{M_1}}','d'=>'Lighter gases diffuse faster','v'=>'r = rate of diffusion, M = molar mass','l'=>3],
  ]],
  ['name'=>'Acids, Bases & Titration','tl'=>2,'formulas'=>[
   ['n'=>'pH Definition','f'=>'\\text{pH}=-\\log_{10}[\\text{H}^+]','d'=>'Measures concentration of hydrogen ions in solution','v'=>'[H⁺] = molar concentration of H⁺ ions (mol/dm³)','l'=>2],
   ['n'=>'pOH Definition','f'=>'\\text{pOH}=-\\log_{10}[\\text{OH}^-]','d'=>'Measures concentration of hydroxide ions','v'=>'[OH⁻] = molar concentration of OH⁻ (mol/dm³)','l'=>3],
   ['n'=>'pH + pOH = 14','f'=>'\\text{pH}+\\text{pOH}=14\\;(25°C)','d'=>'Constant relationship at room temperature (25°C)','v'=>'Valid at 25°C (298 K) only','l'=>2],
   ['n'=>'Water Ionic Product','f'=>'K_w=[\\text{H}^+][\\text{OH}^-]=10^{-14}','d'=>'Product of H⁺ and OH⁻ concentrations in pure water at 25°C','v'=>'K_w = 1×10⁻¹⁴ at 25°C','l'=>3],
   ['n'=>'Titration (1:1 stoichiometry)','f'=>'C_aV_a=C_bV_b','d'=>'At equivalence point for a 1:1 acid-base reaction','v'=>'C = concentration, V = volume; subscripts a = acid, b = base','l'=>2],
   ['n'=>'Titration (general)','f'=>'\\dfrac{C_aV_a}{n_a}=\\dfrac{C_bV_b}{n_b}','d'=>'Corrects for non-1:1 stoichiometric ratio','v'=>'n_a,n_b = stoichiometric coefficients from balanced equation','l'=>3],
  ]],
  ['name'=>'Electrochemistry','tl'=>3,'formulas'=>[
   ['n'=>'Charge in Electrolysis','f'=>'Q=It','d'=>'Total electrical charge passed through the electrolyte','v'=>'I = current (A), t = time (s), Q = charge (C)','l'=>2],
   ['n'=>'Faraday\'s First Law','f'=>'m=\\dfrac{ItM}{nF}','d'=>'Mass deposited or dissolved at an electrode during electrolysis','v'=>'I = current (A), t = time (s), M = molar mass, n = electrons per ion, F = 96500 C/mol','l'=>3],
   ['n'=>'Faraday\'s Second Law','f'=>'\\dfrac{m_1}{m_2}=\\dfrac{M_1/n_1}{M_2/n_2}','d'=>'Same charge deposits masses proportional to equivalent mass','v'=>'m = mass deposited, M = molar mass, n = electron number per ion','l'=>4],
  ]],
  ['name'=>'Chemical Equilibrium','tl'=>4,'formulas'=>[
   ['n'=>'Equilibrium Constant Kc','f'=>'K_c=\\dfrac{[C]^c[D]^d}{[A]^a[B]^b}','d'=>'For reaction aA+bB ⇌ cC+dD at constant temperature','v'=>'[…] = equilibrium molar concentrations; a,b,c,d = stoichiometric coefficients','l'=>3],
   ['n'=>'Le Chatelier (concentration)','f'=>'\\text{Add reactant}\\Rightarrow\\text{equilibrium shifts right}','d'=>'System opposes the disturbance by shifting position','v'=>'Rule: shift direction that partially undoes the change','l'=>3],
   ['n'=>'Reaction Quotient Qc','f'=>'Q_c < K_c \\Rightarrow\\text{forward reaction}\\;\\;Q_c > K_c\\Rightarrow\\text{reverse}','d'=>'Compare Qc with Kc to predict direction of shift','v'=>'Qc uses current (not equilibrium) concentrations','l'=>4],
  ]],
  ['name'=>'Thermochemistry','tl'=>3,'formulas'=>[
   ['n'=>'Enthalpy Change','f'=>'\\Delta H=H_{\\text{products}}-H_{\\text{reactants}}','d'=>'ΔH < 0 = exothermic; ΔH > 0 = endothermic','v'=>'H = enthalpy content (kJ/mol)','l'=>2],
   ['n'=>'Heat of Reaction (calorimetry)','f'=>'q=mc\\Delta T','d'=>'Heat absorbed or released in a reaction measured in solution','v'=>'m = mass of solution, c = 4.18 J/g·°C for water, ΔT = temperature change','l'=>2],
   ['n'=>'Hess\'s Law','f'=>'\\Delta H_{\\text{overall}}=\\sum\\Delta H_{\\text{steps}}','d'=>'Total enthalpy change is independent of the pathway taken','v'=>'ΔH for each step in the thermochemical cycle','l'=>3],
   ['n'=>'Bond Energy (enthalpy)','f'=>'\\Delta H=\\Sigma\\,BE(\\text{broken})-\\Sigma\\,BE(\\text{formed})','d'=>'Energy in = breaking bonds; energy out = forming bonds','v'=>'BE = bond enthalpy in kJ/mol','l'=>4],
  ]],
  ['name'=>'Rate of Reaction','tl'=>3,'formulas'=>[
   ['n'=>'Rate of Reaction','f'=>'\\text{Rate}=\\dfrac{\\Delta[\\text{conc}]}{\\Delta t}','d'=>'Change in molar concentration per unit time','v'=>'Δ[conc] = change in concentration (mol/dm³), Δt = time (s)','l'=>2],
   ['n'=>'Arrhenius Equation','f'=>'k=Ae^{-E_a/RT}','d'=>'Rate constant rises exponentially with temperature','v'=>'A = frequency factor, E_a = activation energy (J/mol), R = 8.314, T = K','l'=>5],
  ]],
 ]],

 'Further Mathematics'=>['emoji'=>'🔢','color'=>'#a78bfa','topics'=>[
  ['name'=>'Matrices','tl'=>3,'formulas'=>[
   ['n'=>'2×2 Determinant','f'=>'\\det\\begin{pmatrix}a&b\\\\c&d\\end{pmatrix}=ad-bc','d'=>'Scalar value for a 2×2 matrix','v'=>'a,b,c,d = matrix entries','l'=>3],
   ['n'=>'Inverse of 2×2 Matrix','f'=>'A^{-1}=\\dfrac{1}{ad-bc}\\begin{pmatrix}d&-b\\\\-c&a\\end{pmatrix}','d'=>'Exists only when det A ≠ 0','v'=>'a,b,c,d = entries; det A = ad−bc','l'=>3],
   ['n'=>'Matrix Multiplication (2×2)','f'=>'\\begin{pmatrix}a&b\\\\c&d\\end{pmatrix}\\!\\begin{pmatrix}e&f\\\\g&h\\end{pmatrix}=\\begin{pmatrix}ae+bg&af+bh\\\\ce+dg&cf+dh\\end{pmatrix}','d'=>'Row × Column multiplication rule; order matters','v'=>'a–h = matrix entries','l'=>4],
  ]],
  ['name'=>'Vectors','tl'=>3,'formulas'=>[
   ['n'=>'Magnitude of 3D Vector','f'=>'|\\mathbf{a}|=\\sqrt{a_1^2+a_2^2+a_3^2}','d'=>'Length (modulus) of vector a = (a₁, a₂, a₃)','v'=>'a₁,a₂,a₃ = components along x, y, z axes','l'=>3],
   ['n'=>'Dot Product','f'=>'\\mathbf{a}\\cdot\\mathbf{b}=|\\mathbf{a}||\\mathbf{b}|\\cos\\theta=a_1b_1+a_2b_2','d'=>'Scalar product; equals zero if vectors are perpendicular','v'=>'θ = angle between a and b','l'=>3],
   ['n'=>'Unit Vector','f'=>'\\hat{\\mathbf{a}}=\\dfrac{\\mathbf{a}}{|\\mathbf{a}|}','d'=>'Vector of magnitude 1 in the same direction as a','v'=>'a = any non-zero vector','l'=>3],
  ]],
  ['name'=>'Complex Numbers','tl'=>4,'formulas'=>[
   ['n'=>'Imaginary Unit','f'=>'i^2=-1,\\;i=\\sqrt{-1}','d'=>'Definition of the imaginary unit','v'=>'i = imaginary unit','l'=>3],
   ['n'=>'Modulus','f'=>'|z|=\\sqrt{a^2+b^2}','d'=>'Distance from origin to z = a + bi in Argand diagram','v'=>'a = real part, b = imaginary part','l'=>3],
   ['n'=>'Argument','f'=>'\\arg(z)=\\tan^{-1}\\!\\left(\\dfrac{b}{a}\\right)','d'=>'Angle the complex number makes with positive real axis','v'=>'a = real part, b = imaginary part (consider quadrant)','l'=>4],
   ['n'=>'Polar Form','f'=>'z=r(\\cos\\theta+i\\sin\\theta)','d'=>'Express complex number using modulus and argument','v'=>'r = modulus, θ = argument (in radians or degrees)','l'=>4],
   ['n'=>'De Moivre\'s Theorem','f'=>'(\\cos\\theta+i\\sin\\theta)^n=\\cos n\\theta+i\\sin n\\theta','d'=>'Raise complex number in polar form to any integer power','v'=>'n = integer, θ = argument','l'=>5],
  ]],
  ['name'=>'Differentiation (Further)','tl'=>4,'formulas'=>[
   ['n'=>'Quotient Rule','f'=>'\\dfrac{d}{dx}\\!\\left(\\dfrac{u}{v}\\right)=\\dfrac{v\\,u\'-u\\,v\'}{v^2}','d'=>'Differentiate a ratio of two functions','v'=>"u,v = functions of x; u',v' = their derivatives; v ≠ 0",'l'=>4],
   ['n'=>'Parametric Differentiation','f'=>'\\dfrac{dy}{dx}=\\dfrac{dy/dt}{dx/dt}','d'=>'Find dy/dx when x and y are both given in terms of t','v'=>'t = parameter; dy/dt and dx/dt = individual derivatives','l'=>4],
  ]],
  ['name'=>'Integration (Further)','tl'=>5,'formulas'=>[
   ['n'=>'Integration by Parts','f'=>'\\int u\\,dv=uv-\\int v\\,du','d'=>'Used when integrand is a product of two functions','v'=>'u,v = chosen functions; du,dv = their differentials','l'=>5],
   ['n'=>'Volume of Revolution (x-axis)','f'=>'V=\\pi\\int_a^b[f(x)]^2\\,dx','d'=>'Volume when area under f(x) is rotated about x-axis','v'=>'f(x) = function, a,b = limits','l'=>5],
  ]],
 ]],
];

$FJ=json_encode($D,JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Formula Panel — Excellent Simplified</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" onload="boot()"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══ DESIGN TOKENS — DARK (default) ══ */
:root{
  --bg:#04020e;
  --s1:#0d1017;
  --s2:#131720;
  --s3:#1a1f2e;
  --s4:#202638;
  --border:rgba(255,255,255,.07);
  --border2:rgba(255,255,255,.14);
  --accent:#00c98a;
  --blue:#3b82f6;
  --amber:#f59e0b;
  --danger:#ff4757;
  --purple:#a78bfa;
  --text:#e8ecf4;
  --sub:#8a93ab;
  --dim:#4a5268;
  --topbar-bg:rgba(10,12,18,.96);
  --shadow:0 4px 24px rgba(0,0,0,.5);
}

/* ══ DESIGN TOKENS — LIGHT ══ */
body.light{
  --bg:#f0f4fc;
  --s1:#ffffff;
  --s2:#eef1f8;
  --s3:#e2e6f0;
  --s4:#d6dcea;
  --border:rgba(0,0,0,.08);
  --border2:rgba(0,0,0,.16);
  --accent:#00a870;
  --blue:#2563eb;
  --amber:#d97706;
  --danger:#e53e3e;
  --purple:#7c3aed;
  --text:#0d1526;
  --sub:#4a5368;
  --dim:#9aa3b8;
  --topbar-bg:rgba(240,244,252,.97);
  --shadow:0 4px 24px rgba(0,0,0,.12);
}

/* ══ RESET ══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;-webkit-tap-highlight-color:transparent}
body{
  height:100%;background:var(--bg);color:var(--text);
  font-family:'Plus Jakarta Sans',sans-serif;
  -webkit-font-smoothing:antialiased;
  overflow:hidden;
  transition:background .25s,color .25s;
}

/* ══ TOPBAR ══ */
.topbar{
  height:56px;
  background:var(--topbar-bg);
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;
  padding:0 14px;gap:8px;
  flex-shrink:0;position:relative;z-index:50;
}
.tb-logo{
  width:30px;height:30px;border-radius:8px;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;
}
.tb-title{font-family:'DM Serif Display',serif;font-size:16px;white-space:nowrap}
.tb-tag{
  font-family:'Space Mono',monospace;font-size:9px;color:var(--dim);
  padding:2px 7px;border-radius:20px;border:1px solid var(--border);
  background:var(--s2);white-space:nowrap;
}
.tb-flex{flex:1;min-width:0}

/* Icon-style button (square) */
.tb-icon-btn{
  width:34px;height:34px;border-radius:9px;flex-shrink:0;
  background:var(--s2);border:1px solid var(--border);
  color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:13px;transition:all .15s;text-decoration:none;
}
.tb-icon-btn:hover{color:var(--text);border-color:var(--border2)}
.tb-icon-btn.active{color:var(--text);border-color:var(--border2);background:var(--s3)}

/* Theme toggle pill */
.theme-pill{
  display:flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:20px;
  background:var(--s2);border:1px solid var(--border);
  cursor:pointer;font-size:12px;font-weight:600;color:var(--sub);
  transition:all .15s;white-space:nowrap;flex-shrink:0;
}
.theme-pill:hover{color:var(--text);border-color:var(--border2)}
.theme-track{
  width:28px;height:16px;border-radius:20px;background:var(--border2);
  position:relative;transition:background .25s;flex-shrink:0;
}
body.light .theme-track{background:var(--accent)}
.theme-thumb{
  position:absolute;top:2px;left:2px;width:12px;height:12px;
  border-radius:50%;background:#fff;
  transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.3);
}
body.light .theme-thumb{transform:translateX(12px)}

/* User chip */
.user-chip{
  display:flex;align-items:center;gap:6px;
  padding:4px 10px;background:var(--s2);
  border:1px solid var(--border);border-radius:20px;
  font-size:12px;font-weight:600;flex-shrink:0;
}
.user-chip img{width:20px;height:20px;border-radius:5px;object-fit:cover}
.user-av{
  width:20px;height:20px;border-radius:5px;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--blue));
  display:flex;align-items:center;justify-content:center;
  font-family:'Space Mono',monospace;font-size:8px;font-weight:700;color:#000;
}

/* Level legend */
.level-legend{display:flex;gap:8px;flex-shrink:0}
.ll{display:flex;align-items:center;gap:4px;font-size:10px;color:var(--dim);font-family:'Space Mono',monospace}
.ll-dot{width:7px;height:7px;border-radius:50%}

/* ══ LAYOUT ══ */
.app-body{
  display:flex;
  height:calc(100dvh - 56px); /* dvh = dynamic viewport (handles mobile browser chrome) */
  overflow:hidden;
}

/* ══ SIDEBAR ══ */
.sidebar{
  width:220px;flex-shrink:0;
  background:var(--s1);border-right:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;
  z-index:40;transition:background .25s;
}
.sb-search{
  display:flex;align-items:center;gap:7px;
  background:var(--s2);border:1.5px solid var(--border);
  border-radius:10px;padding:9px 12px;
  margin:12px 10px 6px;transition:border-color .15s;
}
.sb-search:focus-within{border-color:var(--accent)}
.sb-search i{color:var(--dim);font-size:12px;flex-shrink:0}
.sb-search input{
  flex:1;background:none;border:none;outline:none;
  color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;
}
.sb-search input::placeholder{color:var(--dim)}
.sb-inner{flex:1;overflow-y:auto;padding:6px 8px}
.sb-inner::-webkit-scrollbar{width:3px}
.sb-inner::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}
.sb-section{
  font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--dim);
  padding:8px 6px 5px;
}
.subj-btn{
  width:100%;display:flex;align-items:center;gap:9px;
  padding:9px 8px;border-radius:10px;border:1.5px solid transparent;
  background:transparent;cursor:pointer;text-align:left;
  color:var(--sub);transition:all .15s;margin-bottom:2px;
  -webkit-tap-highlight-color:transparent;
}
.subj-btn:hover{background:var(--s2);color:var(--text)}
.subj-btn.active{background:var(--s2);border-color:var(--border2);color:var(--text)}
.subj-icon{
  width:34px;height:34px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;flex-shrink:0;
}
.subj-meta{flex:1;min-width:0}
.subj-name{font-size:12.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.subj-cnt{font-size:10px;color:var(--dim);font-family:'Space Mono',monospace;margin-top:1px}
.sb-links{padding:8px;border-top:1px solid var(--border);flex-shrink:0}
.sb-link{
  display:flex;align-items:center;gap:8px;padding:9px 8px;
  border-radius:9px;text-decoration:none;color:var(--dim);
  font-size:12px;font-weight:500;transition:all .15s;
}
.sb-link:hover{background:var(--s2);color:var(--text)}
.sb-link i{width:14px;text-align:center;font-size:12px}

/* ══ MAIN CONTENT AREA ══ */
.main{flex:1;display:flex;flex-direction:column;min-width:0;overflow:hidden}

.topic-bar{
  padding:10px 14px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:7px;flex-shrink:0;
  background:var(--s1);overflow-x:auto;scrollbar-width:none;
  -webkit-overflow-scrolling:touch;
  transition:background .25s;
}
.topic-bar::-webkit-scrollbar{display:none}
.tpill{
  padding:6px 13px;border-radius:20px;border:1.5px solid var(--border);
  background:var(--s2);cursor:pointer;font-size:12px;font-weight:600;
  color:var(--sub);white-space:nowrap;transition:all .15s;flex-shrink:0;
  -webkit-tap-highlight-color:transparent;
}
.tpill:hover{color:var(--text);border-color:var(--border2)}
.tpill.on{background:rgba(0,201,138,.1);border-color:rgba(0,201,138,.4);color:var(--accent)}
body.light .tpill.on{background:rgba(0,168,112,.1);border-color:rgba(0,168,112,.35)}

/* ══ FORMULA SCROLL AREA ══ */
.fscroll{
  flex:1;overflow-y:auto;
  padding:16px 14px 80px; /* bottom padding for mobile nav bar */
  -webkit-overflow-scrolling:touch;
}
.fscroll::-webkit-scrollbar{width:5px}
.fscroll::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}

/* Subject banner */
.s-banner{
  margin-bottom:20px;padding-bottom:14px;
  border-bottom:1px solid var(--border);
}
.s-banner-row{display:flex;align-items:center;gap:12px;margin-bottom:6px}
.s-banner-icon{
  width:44px;height:44px;border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  font-size:22px;flex-shrink:0;
}
.s-banner-title{font-family:'DM Serif Display',serif;font-size:22px}
.s-banner-meta{font-size:13px;color:var(--sub);margin-top:2px}
.s-stats{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.s-stat{
  padding:3px 10px;border-radius:20px;
  font-family:'Space Mono',monospace;font-size:10px;
  background:var(--s2);border:1px solid var(--border);color:var(--dim);
}

/* Topic section */
.tsec{margin-bottom:22px}
.tsec-head{
  display:flex;align-items:center;gap:8px;
  margin-bottom:10px;cursor:pointer;
  padding:8px 10px;border-radius:10px;
  background:var(--s2);border:1px solid var(--border);
  user-select:none;transition:background .15s;
  -webkit-tap-highlight-color:transparent;
}
.tsec-head:hover{background:var(--s3)}
.tsec-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.tsec-name{font-size:13px;font-weight:700;flex:1;min-width:0}
.tsec-badge{
  padding:2px 8px;border-radius:20px;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;
  white-space:nowrap;flex-shrink:0;
}
.tsec-cnt{
  font-size:10px;color:var(--dim);
  font-family:'Space Mono',monospace;white-space:nowrap;flex-shrink:0;
}
.tsec-arrow{color:var(--dim);font-size:11px;transition:transform .22s;flex-shrink:0}
.tsec-arrow.open{transform:rotate(90deg)}

/* ══ FORMULA GRID ══ */
.fgrid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(290px,1fr));
  gap:10px;margin-top:4px;
}

/* ══ FORMULA CARD ══ */
.fcard{
  background:var(--s2);border:1.5px solid var(--border);
  border-radius:14px;overflow:hidden;
  display:flex;flex-direction:column;
  transition:border-color .18s, box-shadow .18s, transform .18s;
}
.fcard:hover{
  border-color:var(--border2);
  transform:translateY(-2px);
  box-shadow:var(--shadow);
}
body.light .fcard{box-shadow:0 1px 4px rgba(0,0,0,.06)}
body.light .fcard:hover{box-shadow:0 6px 24px rgba(0,0,0,.12)}

.fcard-top{
  padding:11px 14px 8px;
  display:flex;align-items:flex-start;justify-content:space-between;gap:8px;
}
.fname{font-size:13px;font-weight:700;color:var(--text);line-height:1.35;flex:1}
.flevel{
  padding:2px 7px;border-radius:20px;
  font-family:'Space Mono',monospace;font-size:9px;font-weight:700;
  flex-shrink:0;margin-top:1px;white-space:nowrap;
}
.lv1{background:rgba(0,201,138,.12);color:var(--accent);border:1px solid rgba(0,201,138,.25)}
.lv2{background:rgba(59,130,246,.12);color:#7bb8fc;border:1px solid rgba(59,130,246,.25)}
.lv3{background:rgba(245,158,11,.1);color:#fbbf24;border:1px solid rgba(245,158,11,.25)}
.lv4{background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.2)}
.lv5{background:rgba(167,114,250,.1);color:var(--purple);border:1px solid rgba(167,114,250,.25)}
body.light .lv2{color:#1d6ef5}
body.light .lv3{color:#b45309}
body.light .lv4{color:#c53030}
body.light .lv5{color:#7c3aed}

/* Formula display box */
.fbox{
  margin:0 14px 10px;padding:13px 10px;
  border-radius:10px;background:var(--bg);
  border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  min-height:54px;font-size:15px;text-align:center;line-height:1.8;
  overflow-x:auto;flex-shrink:0;
  /* prevent text selection during scroll on mobile */
  -webkit-overflow-scrolling:touch;
}
body.light .fbox{background:var(--s1)}

/* Description */
.fbody{padding:0 14px 10px;flex:1}
.fdesc{font-size:12.5px;color:var(--sub);line-height:1.55;margin-bottom:7px}
.fvars{
  font-size:11.5px;color:var(--dim);font-style:italic;line-height:1.6;
  padding:6px 10px;border-radius:7px;
  background:var(--s3);border-left:3px solid var(--border2);
}
body.light .fvars{background:var(--s3);border-left-color:var(--blue)}

/* Card footer */
.ffoot{
  padding:9px 14px 11px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  gap:8px;flex-shrink:0;
}
.ffoot-right{display:flex;align-items:center;gap:6px}
.ai-btn{
  display:flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:9px;
  border:1px solid rgba(0,201,138,.3);
  background:rgba(0,201,138,.08);
  color:var(--accent);font-size:11.5px;font-weight:700;cursor:pointer;
  font-family:'Plus Jakarta Sans',sans-serif;
  transition:all .15s;-webkit-tap-highlight-color:transparent;
  min-height:32px; /* bigger touch target */
}
.ai-btn:hover{background:rgba(0,201,138,.16);border-color:rgba(0,201,138,.5);transform:scale(1.02)}
.ai-btn:active{transform:scale(.97)}
body.light .ai-btn{border-color:rgba(0,168,112,.3);background:rgba(0,168,112,.08)}
.copy-btn{
  width:30px;height:30px;border-radius:8px;
  background:var(--s3);border:1px solid var(--border);
  color:var(--dim);cursor:pointer;font-size:11px;
  display:flex;align-items:center;justify-content:center;
  transition:all .15s;-webkit-tap-highlight-color:transparent;
}
.copy-btn:hover{color:var(--text);border-color:var(--border2)}

/* ══ AI EXPLAIN MODAL — BOTTOM SHEET ══ */
.modal-ov{
  display:none;position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.65);backdrop-filter:blur(8px);
  -webkit-backdrop-filter:blur(8px);
  align-items:flex-end;justify-content:center;
  padding-bottom:env(safe-area-inset-bottom);
}
.modal-ov.show{display:flex}
.modal{
  width:100%;max-width:720px;max-height:88dvh;
  background:var(--s1);
  border:1px solid var(--border2);border-bottom:none;
  border-radius:20px 20px 0 0;
  display:flex;flex-direction:column;overflow:hidden;
  animation:slideup .32s cubic-bezier(.22,1,.36,1) both;
  transition:background .25s;
}
@keyframes slideup{from{transform:translateY(100%);opacity:.5}to{transform:translateY(0);opacity:1}}
.modal-handle{display:flex;justify-content:center;padding:10px 0 4px;flex-shrink:0;cursor:grab}
.modal-handle-bar{width:40px;height:4px;border-radius:4px;background:var(--s3)}
.modal-head{
  padding:12px 18px 10px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:10px;flex-shrink:0;
}
.modal-icon{
  width:36px;height:36px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,rgba(0,201,138,.2),rgba(59,130,246,.2));
  border:1px solid rgba(0,201,138,.25);
  display:flex;align-items:center;justify-content:center;
}
.modal-title-wrap{flex:1;min-width:0}
.modal-name{font-size:14px;font-weight:700}
.modal-sub{font-size:11px;color:var(--dim);margin-top:1px}
.modal-x{
  width:30px;height:30px;border-radius:8px;
  background:var(--s2);border:1px solid var(--border);
  color:var(--sub);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;transition:all .15s;flex-shrink:0;
}
.modal-x:hover{color:var(--text);background:var(--s3)}
.modal-fbox{
  margin:12px 16px 8px;padding:14px 12px;
  background:var(--bg);border-radius:11px;
  text-align:center;font-size:18px;
  min-height:52px;display:flex;align-items:center;justify-content:center;
  overflow-x:auto;border:1px solid var(--border);
}
body.light .modal-fbox{background:var(--s2)}
.modal-scroll{flex:1;overflow-y:auto;padding:4px 18px 20px}
.modal-scroll::-webkit-scrollbar{width:4px}
.modal-scroll::-webkit-scrollbar-thumb{background:var(--s3);border-radius:3px}
.ai-resp{font-size:14px;color:var(--sub);line-height:1.85;white-space:pre-wrap}
.ai-loading{display:flex;align-items:center;gap:10px;padding:18px 0;color:var(--dim)}
.adot{
  width:8px;height:8px;border-radius:50%;background:var(--accent);
  animation:ab .9s infinite;
}
.adot:nth-child(2){animation-delay:.15s}
.adot:nth-child(3){animation-delay:.3s}
@keyframes ab{0%,80%,100%{transform:scale(.6);opacity:.4}40%{transform:scale(1);opacity:1}}

/* ══ EMPTY STATE ══ */
.empty{
  display:flex;flex-direction:column;align-items:center;
  justify-content:center;padding:60px 24px;
  text-align:center;color:var(--dim);
}
.empty .big{font-size:44px;margin-bottom:14px;opacity:.5}
.empty p{font-size:14px;line-height:1.7}

/* ══ MOBILE BOTTOM SUBJECT NAV ══ */
.mob-subj-nav{
  display:none; /* shown only on mobile */
  position:fixed;bottom:0;left:0;right:0;z-index:60;
  background:var(--topbar-bg);backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border-top:1px solid var(--border);
  padding:8px 12px 8px;
  padding-bottom:calc(8px + env(safe-area-inset-bottom));
  overflow-x:auto;scrollbar-width:none;
  -webkit-overflow-scrolling:touch;
  gap:6px;flex-shrink:0;
  transition:background .25s;
}
.mob-subj-nav::-webkit-scrollbar{display:none}
.mob-subj-tab{
  display:flex;flex-direction:column;align-items:center;gap:3px;
  min-width:64px;padding:6px 8px;border-radius:11px;
  border:1.5px solid transparent;background:none;
  cursor:pointer;color:var(--dim);transition:all .15s;
  -webkit-tap-highlight-color:transparent;flex-shrink:0;
}
.mob-subj-tab:active{opacity:.7}
.mob-subj-tab.active{background:var(--s2);border-color:var(--border2);color:var(--text)}
.mob-subj-tab-icon{font-size:20px;line-height:1}
.mob-subj-tab-label{font-size:9px;font-weight:700;font-family:'Space Mono',monospace;
  text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}

/* ══ SIDEBAR OVERLAY (mobile) ══ */
.sb-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.55);z-index:39;
  backdrop-filter:blur(2px);
}
.sb-overlay.show{display:block}

/* ══ LEVEL BADGE COLOURS (light mode tweaks) ══ */
body.light .lv1{color:#00845a}
body.light .lv1{color:#007a54}

/* ══ RESPONSIVE BREAKPOINTS ══ */

/* Tablet 768-1024 */
@media(max-width:1024px){
  .sidebar{width:190px}
  .fgrid{grid-template-columns:repeat(auto-fill,minmax(260px,1fr))}
  .level-legend{display:none}
}

/* Mobile ≤768px — full mobile layout */
@media(max-width:768px){
  /* Sidebar becomes a slide-in drawer */
  .sidebar{
    position:fixed;left:0;top:56px;
    height:calc(100dvh - 56px);width:80vw;max-width:300px;
    transform:translateX(-100%);
    transition:transform .28s cubic-bezier(.4,0,.2,1);
    box-shadow:6px 0 32px rgba(0,0,0,.5);
    z-index:40;
  }
  .sidebar.open{transform:translateX(0)}
  .sb-overlay{display:none}
  .sb-overlay.show{display:block}

  /* Hide desktop items not needed */
  .tb-tag{display:none}
  .level-legend{display:none}
  .user-chip span{display:none} /* hide name, keep avatar */
  .user-chip{padding:4px 6px}

  /* Adjust layout for bottom nav */
  .app-body{height:calc(100dvh - 56px)}
  .fscroll{padding:12px 12px 90px} /* extra bottom for nav bar */

  /* Show bottom nav */
  .mob-subj-nav{display:flex}

  /* Menu button visible */
  .menu-btn{display:inline-flex !important}

  /* Topic bar smaller */
  .topic-bar{padding:8px 12px}
  .tpill{padding:5px 11px;font-size:11.5px}

  /* Formula grid single column */
  .fgrid{grid-template-columns:1fr}

  /* Subject banner compact */
  .s-banner-title{font-size:18px}
  .s-banner-icon{width:38px;height:38px;font-size:18px}
  .s-banner-meta{font-size:12px}

  /* AI Modal full height on mobile */
  .modal{max-height:92dvh;border-radius:16px 16px 0 0}

  /* Topic section header smaller */
  .tsec-head{padding:7px 10px}
  .tsec-name{font-size:12.5px}
  .tsec-badge{display:none} /* save space */

  /* Larger touch targets for cards */
  .ai-btn{padding:8px 13px;font-size:12px;min-height:36px}
  .copy-btn{width:34px;height:34px}
  .fcard:hover{transform:none;box-shadow:none} /* disable hover lift on touch */
}

/* Very small screens ≤380px */
@media(max-width:380px){
  .fbox{font-size:13px}
  .fname{font-size:12px}
  .mob-subj-tab{min-width:56px}
  .tb-logo{width:26px;height:26px;font-size:9px}
  .tb-title{font-size:14px}
}

/* Landscape mobile — fix height */
@media(max-height:500px) and (orientation:landscape){
  .modal{max-height:98dvh}
  .topbar{height:46px}
  .app-body{height:calc(100dvh - 46px)}
}

/* Default hide menu btn (desktop) */
.menu-btn{display:none}
</style>
</head>
<body>

<!-- Mobile sidebar overlay -->
<div class="sb-overlay" id="sbOv" onclick="closeSb()"></div>

<!-- AI Explain Modal (bottom sheet) -->
<div class="modal-ov" id="modalOv" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-handle"><div class="modal-handle-bar"></div></div>
    <div class="modal-head">
      <div class="modal-icon"><i class="fa fa-robot" style="color:var(--accent);font-size:14px"></i></div>
      <div class="modal-title-wrap">
        <div class="modal-name" id="mTitle">Formula Explanation</div>
        <div class="modal-sub" id="mSub">AI Tutor · Excellent Simplified</div>
      </div>
      <button class="modal-x" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="modal-fbox" id="mFbox"></div>
    <div class="modal-scroll">
      <div class="ai-loading" id="mLoad">
        <div class="adot"></div><div class="adot"></div><div class="adot"></div>
        <span style="font-size:13px">AI Tutor is explaining…</span>
      </div>
      <div class="ai-resp" id="mResp" style="display:none"></div>
    </div>
  </div>
</div>

<!-- ══ TOPBAR ══ -->
<nav class="topbar">
  <!-- Hamburger (mobile only) -->
  <button class="tb-icon-btn menu-btn" id="menuBtn" onclick="toggleSb()" aria-label="Open subjects">
    <i class="fa fa-bars"></i>
  </button>

  <div class="tb-logo">ES</div>
  <span class="tb-title">Formula Panel</span>
  <span class="tb-tag">JAMB</span>

  <div class="tb-flex"></div>

  <!-- Level legend (desktop) -->
  <div class="level-legend">
    <span class="ll"><span class="ll-dot" style="background:var(--accent)"></span>Foundation</span>
    <span class="ll"><span class="ll-dot" style="background:#7bb8fc"></span>Basic</span>
    <span class="ll"><span class="ll-dot" style="background:#fbbf24"></span>Intermediate</span>
    <span class="ll"><span class="ll-dot" style="background:#f87171"></span>Advanced</span>
    <span class="ll"><span class="ll-dot" style="background:var(--purple)"></span>Expert</span>
  </div>

  <!-- Theme toggle -->
  <button class="theme-pill" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme">
    <span id="themeIcon">🌙</span>
    <div class="theme-track"><div class="theme-thumb"></div></div>
    <span id="themeLabel">Dark</span>
  </button>

  <!-- User chip -->
  <div class="user-chip">
    <?php if($dp):?>
      <img src="<?=htmlspecialchars($dp)?>" alt="">
    <?php else:?>
      <div class="user-av"><?=htmlspecialchars(mb_strtoupper(mb_substr($dn,0,1)))?></div>
    <?php endif;?>
    <span><?=htmlspecialchars($dn)?></span>
  </div>

  <!-- Dashboard link -->
  <a href="dashboard.php" class="tb-icon-btn" title="Dashboard">
    <i class="fa fa-house"></i>
  </a>
</nav>

<div class="app-body">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sb-search">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" id="searchIn" placeholder="Search formulas…" oninput="doSearch(this.value)">
    </div>
    <div class="sb-inner">
      <div class="sb-section">Subjects</div>
      <div id="sbList"></div>
    </div>
    <div class="sb-links">
      <a href="exams/practice_test.php" class="sb-link"><i class="fa fa-clipboard-check" style="width:14px"></i>Practice Tests</a>
      <a href="ai/ai_helper.php" class="sb-link"><i class="fa fa-robot" style="width:14px"></i>AI Tutor</a>
      <a href="questions/live_brainstorm.php" class="sb-link"><i class="fa fa-bolt" style="width:14px"></i>Brainstorm</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <div class="topic-bar" id="topicBar"></div>
    <div class="fscroll" id="fscroll"></div>
  </div>
</div>

<!-- ══ MOBILE BOTTOM SUBJECT NAVIGATION ══ -->
<nav class="mob-subj-nav" id="mobSubjNav"></nav>

<script>
const DATA=<?=$FJ?>;
const LN=['','Foundation','Basic','Intermediate','Advanced','Expert'];
const LC=['','lv1','lv2','lv3','lv4','lv5'];
const LCOL=['','var(--accent)','#7bb8fc','#fbbf24','#f87171','var(--purple)'];
let curSubj=null,curTopic='all',searchQ='',collapsed=new Set();

/* ══ THEME ══ */
function applyTheme(light){
  document.body.classList.toggle('light',light);
  const icon=document.getElementById('themeIcon');
  const lbl=document.getElementById('themeLabel');
  if(icon) icon.textContent=light?'☀️':'🌙';
  if(lbl)  lbl.textContent =light?'Light':'Dark';
}
function toggleTheme(){
  const isLight=!document.body.classList.contains('light');
  applyTheme(isLight);
  try{localStorage.setItem('es_formula_theme',isLight?'light':'dark');}catch(e){}
}
(function initTheme(){
  try{
    const saved=localStorage.getItem('es_formula_theme');
    const prefersDark=window.matchMedia('(prefers-color-scheme:dark)').matches;
    applyTheme(saved?saved==='light':!prefersDark);
  }catch(e){applyTheme(false);}
})();

function boot(){
  buildSb();
  buildMobNav();
  selectSubj(Object.keys(DATA)[0]);
}

/* ══ SIDEBAR ══ */
function buildSb(){
  const el=document.getElementById('sbList');
  el.innerHTML='';
  Object.entries(DATA).forEach(([s,info])=>{
    const tot=info.topics.reduce((a,t)=>a+t.formulas.length,0);
    const b=document.createElement('div');
    b.className='subj-btn'; b.dataset.s=s;
    b.innerHTML=`<div class="subj-icon" style="background:${info.color}18;border:1px solid ${info.color}30">${info.emoji}</div>
      <div class="subj-meta"><div class="subj-name">${s}</div><div class="subj-cnt">${tot} formulas</div></div>`;
    b.onclick=()=>{selectSubj(s);closeSb();};
    el.appendChild(b);
  });
}

/* ══ MOBILE BOTTOM NAV ══ */
function buildMobNav(){
  const nav=document.getElementById('mobSubjNav');
  if(!nav)return;
  nav.innerHTML='';
  Object.entries(DATA).forEach(([s,info])=>{
    const btn=document.createElement('button');
    btn.className='mob-subj-tab'; btn.dataset.s=s;
    btn.innerHTML=`<span class="mob-subj-tab-icon">${info.emoji}</span>
      <span class="mob-subj-tab-label">${s.length>8?s.split(' ')[0]:s}</span>`;
    btn.onclick=()=>selectSubj(s);
    nav.appendChild(btn);
  });
}

function syncMobNav(){
  document.querySelectorAll('.mob-subj-tab').forEach(b=>{
    b.classList.toggle('active',b.dataset.s===curSubj);
  });
}

function selectSubj(s){
  curSubj=s; curTopic='all'; searchQ='';
  document.getElementById('searchIn').value='';
  document.querySelectorAll('.subj-btn').forEach(b=>{
    const on=b.dataset.s===s;
    b.classList.toggle('active',on);
    b.style.borderColor=on?DATA[s].color+'44':'transparent';
  });
  syncMobNav();
  buildTopicBar(s); render();
}

/* ── TOPIC BAR ── */
function buildTopicBar(s){
  const bar=document.getElementById('topicBar');
  bar.innerHTML='<div class="tpill on" data-t="all" onclick="setTopic(\'all\',this)">All Topics</div>';
  DATA[s].topics.forEach(t=>{
    const p=document.createElement('div');
    p.className='tpill'; p.dataset.t=t.name; p.textContent=t.name;
    p.onclick=()=>setTopic(t.name,p);
    bar.appendChild(p);
  });
}

function setTopic(t,el){
  curTopic=t;
  document.querySelectorAll('.tpill').forEach(p=>p.classList.remove('on'));
  el.classList.add('on');
  render();
}

/* ── SEARCH ── */
function doSearch(v){
  searchQ=v.toLowerCase().trim();
  if(searchQ) renderSearch(); else render();
}

function renderSearch(){
  const scroll=document.getElementById('fscroll');
  let res=[];
  Object.entries(DATA).forEach(([s,info])=>{
    info.topics.forEach(t=>{
      t.formulas.forEach(f=>{
        if((f.n+f.d+f.v+s+t.name).toLowerCase().includes(searchQ))
          res.push({s,t:t.name,f,col:info.color});
      });
    });
  });
  if(!res.length){
    scroll.innerHTML=`<div class="empty"><div class="big">🔍</div><p>No formulas found for "<strong>${esc(searchQ)}</strong>".</p></div>`;
    return;
  }
  let h=`<div class="s-banner"><div class="s-banner-row"><div class="s-banner-title">Search Results</div></div>
    <div class="s-banner-meta">${res.length} formula${res.length!==1?'s':''} matching "<strong>${esc(searchQ)}</strong>"</div></div>
    <div class="fgrid">`;
  res.forEach(r=>{ h+=card(r.f,r.col,r.s); });
  h+='</div>';
  scroll.innerHTML=h; rk();
}

/* ── MAIN RENDER ── */
function render(){
  if(searchQ){renderSearch();return;}
  const info=DATA[curSubj];
  const tot=info.topics.reduce((a,t)=>a+t.formulas.length,0);
  let h=`<div class="s-banner">
    <div class="s-banner-row">
      <div class="s-banner-icon" style="background:${info.color}18;border:1px solid ${info.color}25">${info.emoji}</div>
      <div>
        <div class="s-banner-title" style="color:${info.color}">${curSubj}</div>
        <div class="s-banner-meta">JAMB Syllabus · Easiest → Hardest</div>
      </div>
    </div>
    <div class="s-stats">
      <span class="s-stat">${tot} Formulas</span>
      <span class="s-stat">${info.topics.length} Topics</span>
    </div>
  </div>`;

  const topics=curTopic==='all'?info.topics:info.topics.filter(t=>t.name===curTopic);
  if(!topics.length){h+='<div class="empty"><div class="big">📋</div><p>No topics here.</p></div>';}
  else{
    topics.forEach(t=>{
      const id='t'+btoa(unescape(encodeURIComponent(t.name))).replace(/=/g,'');
      const open=!collapsed.has(id);
      const lc=LCOL[Math.round(t.tl)]||LCOL[3];
      const ln=LN[Math.round(t.tl)]||'Intermediate';
      const lclass=LC[Math.round(t.tl)]||'lv3';
      h+=`<div class="tsec" id="ts-${id}">
        <div class="tsec-head" onclick="toggleT('${id}')">
          <div class="tsec-dot" style="background:${lc}"></div>
          <span class="tsec-name">${esc(t.name)}</span>
          <span class="tsec-badge ${lclass}">${ln}</span>
          <span class="tsec-cnt">${t.formulas.length} formulas</span>
          <i class="fa fa-chevron-right tsec-arrow ${open?'open':''}"></i>
        </div>
        <div class="fgrid" id="tg-${id}" style="display:${open?'grid':'none'}">`;
      t.formulas.forEach(f=>{ h+=card(f,info.color,curSubj); });
      h+=`</div></div>`;
    });
  }
  document.getElementById('fscroll').innerHTML=h; rk();
}

function card(f,col,subj){
  const lc=LC[f.l]||'lv3', ln=LN[f.l]||'—';
  const lex=f.f.replace(/'/g,"\\'").replace(/\\/g,'\\\\');
  const nen=f.n.replace(/'/g,"\\'");
  const den=f.d.replace(/'/g,"\\'");
  return `<div class="fcard">
    <div class="fcard-top">
      <div class="fname">${esc(f.n)}</div>
      <span class="flevel ${lc}">${ln}</span>
    </div>
    <div class="fbox">\\[${f.f}\\]</div>
    <div class="fbody">
      <div class="fdesc">${esc(f.d)}</div>
      <div class="fvars">📎 ${esc(f.v)}</div>
    </div>
    <div class="ffoot">
      <button class="ai-btn" onclick="openAI('${nen}','${lex}','${den}','${esc(subj).replace(/'/g,"\\'")}')">
        <i class="fa fa-robot" style="font-size:10px"></i> Ask AI
      </button>
      <div class="ffoot-right">
        <button class="copy-btn" title="Copy LaTeX" onclick="cpLatex(this,'${lex}')">
          <i class="fa fa-copy"></i>
        </button>
      </div>
    </div>
  </div>`;
}

/* ── TOGGLE TOPIC ── */
function toggleT(id){
  const g=document.getElementById('tg-'+id);
  const a=document.querySelector(`#ts-${id} .tsec-arrow`);
  if(!g)return;
  const open=g.style.display!=='none';
  g.style.display=open?'none':'grid';
  a?.classList.toggle('open',!open);
  if(open)collapsed.add(id); else collapsed.delete(id);
}

/* ── KATEX ── */
function rk(){
  if(typeof renderMathInElement==='undefined')return;
  setTimeout(()=>{
    renderMathInElement(document.getElementById('fscroll'),{
      delimiters:[{left:'\\[',right:'\\]',display:true},{left:'\\(',right:'\\)',display:false}],
      throwOnError:false
    });
  },20);
}

/* ── AI MODAL ── */
function openAI(name,latex,desc,subj){
  document.getElementById('mTitle').textContent=name;
  document.getElementById('mSub').textContent=subj+' · AI Explanation';
  document.getElementById('mFbox').innerHTML='\\['+latex+'\\]';
  document.getElementById('mLoad').style.display='flex';
  document.getElementById('mResp').style.display='none';
  document.getElementById('mResp').textContent='';
  document.getElementById('modalOv').classList.add('show');
  document.body.style.overflow='hidden';
  setTimeout(()=>{
    if(typeof renderMathInElement!=='undefined')
      renderMathInElement(document.getElementById('mFbox'),{
        delimiters:[{left:'\\[',right:'\\]',display:true}],throwOnError:false});
  },60);
  callAI(name,latex,desc,subj);
}

async function callAI(name,latex,desc,subj){
  // Routes through ai/textbook_ai.php → Groq (llama-3.1-8b-instant)
  const question=`You are an expert ${subj} teacher for JAMB UTME students in Nigeria.

Explain this formula clearly and concisely:
Name: ${name}
Formula: ${latex}
What it does: ${desc}

Structure your response:
1. PURPOSE — What is this formula used for? (2 sentences max)
2. VARIABLES — Define each symbol clearly
3. KEY CONDITIONS — Any restrictions or special cases to watch out for
4. WORKED EXAMPLE — One JAMB-style question with full step-by-step solution
5. MEMORY TIP — One quick way to remember or recognise this formula

Be student-friendly, thorough but concise.`;
  try{
    const r=await fetch('ai/textbook_ai.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({question})
    });
    const d=await r.json();
    const txt=d.answer||'';
    document.getElementById('mLoad').style.display='none';
    document.getElementById('mResp').style.display='block';
    document.getElementById('mResp').textContent=
      txt||('⚠️ '+(d.error||'No answer returned. Check ai/textbook_ai.php is reachable.'));
  }catch(e){
    document.getElementById('mLoad').style.display='none';
    document.getElementById('mResp').style.display='block';
    document.getElementById('mResp').textContent='⚠️ Network error: '+e.message;
  }
}

function closeModal(){
  document.getElementById('modalOv').classList.remove('show');
  document.body.style.overflow='';
}

/* ── COPY LATEX ── */
async function cpLatex(btn,latex){
  try{
    await navigator.clipboard.writeText(latex.replace(/\\\\/g,'\\'));
    const o=btn.innerHTML; btn.innerHTML='<i class="fa fa-check" style="color:var(--accent)"></i>';
    setTimeout(()=>btn.innerHTML=o,1500);
  }catch(e){}
}

/* ── SIDEBAR MOBILE ── */
function toggleSb(){
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sbOv').classList.toggle('show');
}
function closeSb(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sbOv').classList.remove('show');
}

/* ── UTIL ── */
function esc(s){return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
</script>
</body>
</html>



// ════════════════════════════════════════════════════════════════
// FORMULA DATABASE — JAMB Syllabus, Easy → Hard within each topic
// ════════════════════════════════════════════════════════════════
$FORMULAS = [
'mathematics' => [
  'Number & Numeration' => [
    ['id'=>'m1','name'=>'Index Multiplication','latex'=>'a^m \\times a^n = a^{m+n}','desc'=>'Same base: add exponents.','vars'=>['a'=>'Base','m,n'=>'Exponents'],'level'=>1,'sample_q'=>'2³ × 2⁴ = 2⁷ = 128'],
    ['id'=>'m2','name'=>'Index Division','latex'=>'\\frac{a^m}{a^n} = a^{m-n}','desc'=>'Same base: subtract exponents.','vars'=>['a'=>'Base (≠0)','m,n'=>'Exponents'],'level'=>1,'sample_q'=>'5⁶ ÷ 5² = 5⁴ = 625'],
    ['id'=>'m3','name'=>'Power of Power','latex'=>'(a^m)^n = a^{mn}','desc'=>'Raise a power to a power: multiply exponents.','vars'=>['a'=>'Base','m,n'=>'Exponents'],'level'=>1,'sample_q'=>'(3²)³ = 3⁶ = 729'],
    ['id'=>'m4','name'=>'Negative Index','latex'=>'a^{-n} = \\frac{1}{a^n}','desc'=>'Negative exponent = reciprocal of positive power.','vars'=>['a'=>'Base (≠0)','n'=>'Positive integer'],'level'=>1,'sample_q'=>'2⁻³ = 1/8 = 0.125'],
    ['id'=>'m5','name'=>'Fractional Index','latex'=>'a^{\\frac{1}{n}} = \\sqrt[n]{a}','desc'=>'Fractional index = nth root.','vars'=>['a'=>'Base','n'=>'Root degree'],'level'=>2,'sample_q'=>'27^(1/3) = ∛27 = 3'],
    ['id'=>'m6','name'=>'Log Product Rule','latex'=>'\\log_a(mn) = \\log_a m + \\log_a n','desc'=>'Log of a product = sum of logs.','vars'=>['a'=>'Base','m,n'=>'Positive numbers'],'level'=>2,'sample_q'=>'log(6) = log(2) + log(3)'],
    ['id'=>'m7','name'=>'Log Power Rule','latex'=>'\\log_a m^n = n\\log_a m','desc'=>'Bring the exponent to front of log.','vars'=>['a'=>'Base','m'=>'Argument','n'=>'Power'],'level'=>2,'sample_q'=>'log(8) = 3log(2)'],
    ['id'=>'m8','name'=>'Change of Base','latex'=>'\\log_a b = \\frac{\\log b}{\\log a}','desc'=>'Convert logarithm to any convenient base.','vars'=>['a'=>'Original base','b'=>'Argument'],'level'=>2,'sample_q'=>'log₅25 = log25/log5 = 2'],
    ['id'=>'m9','name'=>'Log-Exponential Duality','latex'=>'\\log_a b = c \\iff a^c = b','desc'=>'Logarithm is the inverse of an exponential.','vars'=>['a'=>'Base','b'=>'Number','c'=>'Log value'],'level'=>3,'sample_q'=>'log₂32=5 because 2⁵=32'],
  ],
  'Algebra' => [
    ['id'=>'m10','name'=>'Quadratic Formula','latex'=>'x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}','desc'=>'Solves ax² + bx + c = 0 for any values.','vars'=>['a,b,c'=>'Coefficients','x'=>'Unknown roots'],'level'=>1,'sample_q'=>'x²-5x+6=0 → x=2 or x=3'],
    ['id'=>'m11','name'=>'Discriminant','latex'=>'\\Delta = b^2 - 4ac','desc'=>'Δ>0: two real roots. Δ=0: equal roots. Δ<0: no real roots.','vars'=>['Δ'=>'Discriminant','a,b,c'=>'Coefficients'],'level'=>1,'sample_q'=>'x²-4x+4: Δ=0 → equal roots'],
    ['id'=>'m12','name'=>'Sum & Product of Roots','latex'=>'\\alpha + \\beta = -\\frac{b}{a}, \\quad \\alpha\\beta = \\frac{c}{a}','desc'=>'Root relationships without solving the quadratic.','vars'=>['α,β'=>'Roots','a,b,c'=>'Coefficients'],'level'=>2,'sample_q'=>'x²-5x+6: sum=5, product=6'],
    ['id'=>'m13','name'=>'AP — nth Term','latex'=>'T_n = a + (n-1)d','desc'=>'nth term of arithmetic progression.','vars'=>['Tₙ'=>'nth term','a'=>'First term','n'=>'Term number','d'=>'Common difference'],'level'=>1,'sample_q'=>'3,7,11,... T₅=3+4(4)=19'],
    ['id'=>'m14','name'=>'AP — Sum','latex'=>'S_n = \\frac{n}{2}[2a + (n-1)d]','desc'=>'Sum of first n terms of AP.','vars'=>['Sₙ'=>'Sum','a'=>'First term','d'=>'Common difference'],'level'=>2,'sample_q'=>'1+3+5+...+19 (10 terms): S₁₀=100'],
    ['id'=>'m15','name'=>'GP — nth Term','latex'=>'T_n = ar^{n-1}','desc'=>'nth term of geometric progression.','vars'=>['Tₙ'=>'nth term','a'=>'First term','r'=>'Common ratio'],'level'=>1,'sample_q'=>'2,6,18,... T₄=2×3³=54'],
    ['id'=>'m16','name'=>'GP — Sum','latex'=>'S_n = \\frac{a(r^n - 1)}{r - 1}','desc'=>'Sum of first n terms of GP (r≠1).','vars'=>['Sₙ'=>'Sum','a'=>'First term','r'=>'Common ratio'],'level'=>2,'sample_q'=>'2+6+18: S₃=2(27-1)/2=26'],
    ['id'=>'m17','name'=>'Infinite GP Sum','latex'=>'S_\\infty = \\frac{a}{1-r}, \\quad |r| < 1','desc'=>'Sum of infinite GP when |r|<1.','vars'=>['S∞'=>'Infinite sum','a'=>'First term','r'=>'Ratio'],'level'=>2,'sample_q'=>'1+½+¼+... = 1/(1-½) = 2'],
    ['id'=>'m18','name'=>'Binomial Theorem (General Term)','latex'=>'T_{r+1} = \\binom{n}{r} a^{n-r} b^r','desc'=>'(r+1)th term in expansion of (a+b)ⁿ.','vars'=>['n'=>'Power','r'=>'Term index (0-based)','ⁿCᵣ'=>'Combination'],'level'=>3,'sample_q'=>'(1+x)³ = 1+3x+3x²+x³'],
  ],
  'Geometry & Trigonometry' => [
    ['id'=>'m19','name'=>'SOH-CAH-TOA','latex'=>'\\sin\\theta=\\frac{opp}{hyp},\\;\\cos\\theta=\\frac{adj}{hyp},\\;\\tan\\theta=\\frac{opp}{adj}','desc'=>'Right-triangle trig ratios.','vars'=>['opp'=>'Opposite side','adj'=>'Adjacent side','hyp'=>'Hypotenuse'],'level'=>1,'sample_q'=>'opp=3,hyp=5 → sinθ=0.6'],
    ['id'=>'m20','name'=>'Pythagorean Identity','latex'=>'\\sin^2\\theta + \\cos^2\\theta = 1','desc'=>'Also: 1+tan²θ=sec²θ and 1+cot²θ=cosec²θ.','vars'=>['θ'=>'Any angle'],'level'=>1,'sample_q'=>'sinθ=3/5,cosθ=4/5: (9+16)/25=1 ✓'],
    ['id'=>'m21','name'=>'Sine Rule','latex'=>'\\frac{a}{\\sin A} = \\frac{b}{\\sin B} = \\frac{c}{\\sin C}','desc'=>'For any triangle. Use with ASA, AAS, SSA.','vars'=>['a,b,c'=>'Sides','A,B,C'=>'Opposite angles'],'level'=>2,'sample_q'=>'a=10,A=30°,B=45°: b≈14.1'],
    ['id'=>'m22','name'=>'Cosine Rule','latex'=>'a^2 = b^2 + c^2 - 2bc\\cos A','desc'=>'For any triangle. Use with SAS or SSS.','vars'=>['a,b,c'=>'Sides','A'=>'Angle opposite a'],'level'=>2,'sample_q'=>'b=4,c=5,A=60°: a=√21≈4.58'],
    ['id'=>'m23','name'=>'Triangle Area (Trig)','latex'=>'\\text{Area} = \\frac{1}{2}ab\\sin C','desc'=>'Area given two sides and included angle.','vars'=>['a,b'=>'Two sides','C'=>'Included angle'],'level'=>2,'sample_q'=>'a=6,b=8,C=30°: Area=12'],
    ['id'=>'m24','name'=>'Double Angle — sin','latex'=>'\\sin 2A = 2\\sin A \\cos A','desc'=>'Expresses sin2A using sinA and cosA.','vars'=>['A'=>'Any angle'],'level'=>3,'sample_q'=>'sin60°=2sin30°cos30°=√3/2 ✓'],
    ['id'=>'m25','name'=>'Double Angle — cos','latex'=>'\\cos 2A = \\cos^2 A - \\sin^2 A = 2\\cos^2A-1','desc'=>'Three equivalent forms of cos2A.','vars'=>['A'=>'Any angle'],'level'=>3,'sample_q'=>'cos60°=1-2sin²30°=½ ✓'],
  ],
  'Calculus' => [
    ['id'=>'m26','name'=>'Power Rule — Differentiation','latex'=>'\\frac{d}{dx}(x^n) = nx^{n-1}','desc'=>'Multiply by exponent, reduce power by 1.','vars'=>['x'=>'Variable','n'=>'Exponent'],'level'=>1,'sample_q'=>'d/dx(x⁴)=4x³'],
    ['id'=>'m27','name'=>'Chain Rule','latex'=>'\\frac{dy}{dx} = \\frac{dy}{du} \\cdot \\frac{du}{dx}','desc'=>'Differentiate composite functions.','vars'=>['y'=>'Outer function','u'=>'Inner function'],'level'=>2,'sample_q'=>'y=(3x+1)⁵: dy/dx=15(3x+1)⁴'],
    ['id'=>'m28','name'=>'Product Rule','latex'=>'\\frac{d}{dx}(uv) = u\\frac{dv}{dx} + v\\frac{du}{dx}','desc'=>'Differentiate a product of two functions.','vars'=>['u,v'=>'Functions of x'],'level'=>2,'sample_q'=>'d/dx(x²sinx)=x²cosx+2xsinx'],
    ['id'=>'m29','name'=>'Power Rule — Integration','latex'=>'\\int x^n\\,dx = \\frac{x^{n+1}}{n+1} + C','desc'=>'Increase power by 1, divide by new power.','vars'=>['x'=>'Variable','n'=>'Exponent (≠-1)','C'=>'Constant'],'level'=>1,'sample_q'=>'∫x³dx = x⁴/4 + C'],
    ['id'=>'m30','name'=>'Definite Integral','latex'=>'\\int_a^b f(x)\\,dx = F(b) - F(a)','desc'=>'Area under curve from a to b.','vars'=>['a,b'=>'Limits','F(x)'=>'Antiderivative'],'level'=>2,'sample_q'=>'∫₀²x²dx=[x³/3]₀²=8/3'],
  ],
  'Statistics & Probability' => [
    ['id'=>'m31','name'=>'Arithmetic Mean','latex'=>'\\bar{x} = \\frac{\\sum x}{n} = \\frac{\\sum fx}{\\sum f}','desc'=>'Average value. Second form for grouped data.','vars'=>['x̄'=>'Mean','f'=>'Frequency','n'=>'Count'],'level'=>1,'sample_q'=>'Mean of 2,4,6,8 = 20/4 = 5'],
    ['id'=>'m32','name'=>'Standard Deviation','latex'=>'\\sigma = \\sqrt{\\frac{\\sum(x-\\bar{x})^2}{n}}','desc'=>'Measures data spread around the mean.','vars'=>['σ'=>'Std deviation','x̄'=>'Mean','n'=>'Count'],'level'=>2,'sample_q'=>'Small σ = data close to mean'],
    ['id'=>'m33','name'=>'Probability','latex'=>'P(A) = \\frac{\\text{Favourable outcomes}}{\\text{Total outcomes}}','desc'=>'Always 0 ≤ P(A) ≤ 1.','vars'=>['P(A)'=>'Probability of A'],'level'=>1,'sample_q'=>'P(head)=1/2=0.5'],
    ['id'=>'m34','name'=>'Addition Rule','latex'=>'P(A \\cup B) = P(A) + P(B) - P(A \\cap B)','desc'=>'Probability of A or B. Subtract overlap.','vars'=>['A∪B'=>'A or B','A∩B'=>'A and B'],'level'=>2,'sample_q'=>'P(red or face)=26/52+12/52-6/52=32/52'],
    ['id'=>'m35','name'=>'Permutation','latex'=>'{}^nP_r = \\frac{n!}{(n-r)!}','desc'=>'Arrangements of r from n (ORDER matters).','vars'=>['n'=>'Total','r'=>'Chosen'],'level'=>2,'sample_q'=>'⁵P₃=60'],
    ['id'=>'m36','name'=>'Combination','latex'=>'{}^nC_r = \\binom{n}{r} = \\frac{n!}{r!(n-r)!}','desc'=>'Selections of r from n (ORDER irrelevant).','vars'=>['n'=>'Total','r'=>'Chosen'],'level'=>2,'sample_q'=>'⁵C₃=10'],
  ],
],
'physics' => [
  'Mechanics' => [
    ['id'=>'p1','name'=>'Speed / Velocity','latex'=>'v = \\frac{s}{t}','desc'=>'Speed = distance/time. Velocity = displacement/time (has direction).','vars'=>['v'=>'Speed (m/s)','s'=>'Distance (m)','t'=>'Time (s)'],'level'=>1,'sample_q'=>'120m in 10s: v=12m/s'],
    ['id'=>'p2','name'=>'Acceleration','latex'=>'a = \\frac{v - u}{t}','desc'=>'Rate of change of velocity.','vars'=>['a'=>'Acceleration (m/s²)','v'=>'Final velocity','u'=>'Initial velocity','t'=>'Time'],'level'=>1,'sample_q'=>'u=5,v=20,t=3: a=5m/s²'],
    ['id'=>'p3','name'=>'SUVAT: v²=u²+2as','latex'=>'v^2 = u^2 + 2as','desc'=>'Kinematic equation — no time needed.','vars'=>['v'=>'Final v','u'=>'Initial v','a'=>'Acceleration','s'=>'Displacement'],'level'=>1,'sample_q'=>'u=0,a=10,s=20: v=20m/s'],
    ['id'=>'p4','name'=>'SUVAT: s=ut+½at²','latex'=>'s = ut + \\frac{1}{2}at^2','desc'=>'Displacement with acceleration and time.','vars'=>['s'=>'Displacement','u'=>'Initial v','a'=>'Acceleration','t'=>'Time'],'level'=>1,'sample_q'=>'u=0,a=10,t=3: s=45m'],
    ['id'=>'p5','name'=>"Newton's 2nd Law",'latex'=>'F = ma','desc'=>'Net force = mass × acceleration.','vars'=>['F'=>'Force (N)','m'=>'Mass (kg)','a'=>'Acceleration (m/s²)'],'level'=>1,'sample_q'=>'m=5kg,a=3: F=15N'],
    ['id'=>'p6','name'=>'Weight','latex'=>'W = mg','desc'=>'Gravitational force on a mass. g≈10m/s² on Earth.','vars'=>['W'=>'Weight (N)','m'=>'Mass (kg)','g'=>'10 m/s²'],'level'=>1,'sample_q'=>'m=6kg: W=60N'],
    ['id'=>'p7','name'=>'Momentum','latex'=>'p = mv','desc'=>'Product of mass and velocity. Vector quantity.','vars'=>['p'=>'Momentum (kg·m/s)','m'=>'Mass','v'=>'Velocity'],'level'=>1,'sample_q'=>'m=2,v=5: p=10kg·m/s'],
    ['id'=>'p8','name'=>'Conservation of Momentum','latex'=>'m_1u_1 + m_2u_2 = m_1v_1 + m_2v_2','desc'=>'Total momentum before = total after collision.','vars'=>['m'=>'Masses','u'=>'Initial velocities','v'=>'Final velocities'],'level'=>2,'sample_q'=>'2×4+3×0=2v₁+3v₂'],
    ['id'=>'p9','name'=>'Kinetic Energy','latex'=>'KE = \\frac{1}{2}mv^2','desc'=>'Energy of motion. Depends on v².','vars'=>['KE'=>'Energy (J)','m'=>'Mass (kg)','v'=>'Velocity (m/s)'],'level'=>1,'sample_q'=>'m=4,v=3: KE=18J'],
    ['id'=>'p10','name'=>'Gravitational PE','latex'=>'PE = mgh','desc'=>'Potential energy due to height.','vars'=>['PE'=>'Energy (J)','m'=>'Mass','g'=>'10m/s²','h'=>'Height (m)'],'level'=>1,'sample_q'=>'m=2,h=5: PE=100J'],
    ['id'=>'p11','name'=>'Work Done','latex'=>'W = Fs\\cos\\theta','desc'=>'Work = 0 when force ⊥ displacement.','vars'=>['W'=>'Work (J)','F'=>'Force (N)','s'=>'Displacement','θ'=>'Angle'],'level'=>1,'sample_q'=>'F=10,s=5,θ=0: W=50J'],
    ['id'=>'p12','name'=>'Power','latex'=>'P = \\frac{W}{t} = Fv','desc'=>'Rate of doing work.','vars'=>['P'=>'Power (W)','W'=>'Work (J)','t'=>'Time (s)'],'level'=>1,'sample_q'=>'W=200J,t=10s: P=20W'],
    ['id'=>'p13','name'=>'Efficiency','latex'=>'\\eta = \\frac{\\text{Useful output}}{\\text{Total input}} \\times 100\\%','desc'=>'Ratio of useful to total energy.','vars'=>['η'=>'Efficiency (%)'],'level'=>2,'sample_q'=>'Input=500J,useful=400J: η=80%'],
    ['id'=>'p14','name'=>'Centripetal Force','latex'=>'F_c = \\frac{mv^2}{r} = mr\\omega^2','desc'=>'Force needed for circular motion, directed to centre.','vars'=>['Fᶜ'=>'Force (N)','m'=>'Mass','v'=>'Speed','r'=>'Radius','ω'=>'Angular velocity'],'level'=>2,'sample_q'=>'m=2,v=4,r=2: Fc=16N'],
    ['id'=>'p15','name'=>'Universal Gravitation','latex'=>'F = \\frac{Gm_1m_2}{r^2}','desc'=>'Inverse square law. G=6.67×10⁻¹¹ Nm²/kg².','vars'=>['F'=>'Force (N)','G'=>'6.67×10⁻¹¹','m₁,m₂'=>'Masses','r'=>'Distance'],'level'=>3,'sample_q'=>'Double distance → force reduces 4×'],
  ],
  'Waves & Optics' => [
    ['id'=>'p16','name'=>'Wave Equation','latex'=>'v = f\\lambda','desc'=>'Wave speed = frequency × wavelength.','vars'=>['v'=>'Speed (m/s)','f'=>'Frequency (Hz)','λ'=>'Wavelength (m)'],'level'=>1,'sample_q'=>'f=440Hz,λ=0.77m: v≈340m/s'],
    ['id'=>'p17','name'=>'Period & Frequency','latex'=>'T = \\frac{1}{f}','desc'=>'Period = time for one complete oscillation.','vars'=>['T'=>'Period (s)','f'=>'Frequency (Hz)'],'level'=>1,'sample_q'=>'f=50Hz: T=0.02s'],
    ['id'=>'p18','name'=>"Snell's Law",'latex'=>'n_1\\sin\\theta_1 = n_2\\sin\\theta_2','desc'=>'Refraction at an interface between two media.','vars'=>['n₁,n₂'=>'Refractive indices','θ₁,θ₂'=>'Angles of incidence/refraction'],'level'=>2,'sample_q'=>'Air→glass(n=1.5), θ₁=30°: sinθ₂=1/3'],
    ['id'=>'p19','name'=>'Refractive Index','latex'=>'n = \\frac{c}{v} = \\frac{\\sin i}{\\sin r}','desc'=>'Ratio of speed of light in vacuum to speed in medium.','vars'=>['n'=>'Refractive index','c'=>'3×10⁸m/s','v'=>'Speed in medium'],'level'=>2,'sample_q'=>'n=1.5: light is 1.5× slower in glass'],
    ['id'=>'p20','name'=>'Mirror/Lens Formula','latex'=>'\\frac{1}{f} = \\frac{1}{u} + \\frac{1}{v}','desc'=>'Relates focal length to object and image distances.','vars'=>['f'=>'Focal length','u'=>'Object distance','v'=>'Image distance'],'level'=>2,'sample_q'=>'f=10,u=15: v=30cm'],
    ['id'=>'p21','name'=>'Magnification','latex'=>'m = \\frac{v}{u}','desc'=>'Image size / object size. Negative = inverted.','vars'=>['m'=>'Magnification','v'=>'Image distance','u'=>'Object distance'],'level'=>2,'sample_q'=>'u=15,v=30: m=2 (twice as large)'],
  ],
  'Heat & Thermodynamics' => [
    ['id'=>'p22','name'=>'Specific Heat Capacity','latex'=>'Q = mc\\Delta\\theta','desc'=>'Heat energy depends on mass, c, and temperature change.','vars'=>['Q'=>'Heat (J)','m'=>'Mass (kg)','c'=>'Specific heat (J/kg°C)','Δθ'=>'Temp change'],'level'=>1,'sample_q'=>'m=2,c=4200,Δθ=10: Q=84000J'],
    ['id'=>'p23','name'=>'Latent Heat','latex'=>'Q = mL','desc'=>'Heat for change of state at CONSTANT temperature.','vars'=>['Q'=>'Heat (J)','m'=>'Mass (kg)','L'=>'Specific latent heat (J/kg)'],'level'=>1,'sample_q'=>'Melt 0.5kg ice: Q=168000J'],
    ['id'=>'p24','name'=>"Boyle's Law",'latex'=>'P_1V_1 = P_2V_2','desc'=>'Constant temperature: PV = constant.','vars'=>['P'=>'Pressure','V'=>'Volume'],'level'=>1,'sample_q'=>'P₁=2,V₁=3L: P₂V₂=6atm·L'],
    ['id'=>'p25','name'=>"Charles's Law",'latex'=>'\\frac{V_1}{T_1} = \\frac{V_2}{T_2}','desc'=>'Constant pressure: V proportional to T(Kelvin).','vars'=>['V'=>'Volume','T'=>'Temperature (K = °C+273)'],'level'=>1,'sample_q'=>'V₁=2L,T₁=300K,T₂=600K: V₂=4L'],
    ['id'=>'p26','name'=>'General Gas Law','latex'=>'\\frac{P_1V_1}{T_1} = \\frac{P_2V_2}{T_2}','desc'=>'Combines Boyle and Charles laws. T must be in Kelvin.','vars'=>['P'=>'Pressure','V'=>'Volume','T'=>'Kelvin'],'level'=>2,'sample_q'=>'Always convert °C → K first'],
  ],
  'Electricity' => [
    ['id'=>'p27','name'=>"Ohm's Law",'latex'=>'V = IR','desc'=>'Voltage = current × resistance.','vars'=>['V'=>'Voltage (V)','I'=>'Current (A)','R'=>'Resistance (Ω)'],'level'=>1,'sample_q'=>'I=2A,R=5Ω: V=10V'],
    ['id'=>'p28','name'=>'Series Resistance','latex'=>'R_T = R_1 + R_2 + R_3','desc'=>'Add resistances. Same current everywhere.','vars'=>['Rₜ'=>'Total resistance'],'level'=>1,'sample_q'=>'3Ω+5Ω=8Ω'],
    ['id'=>'p29','name'=>'Parallel Resistance','latex'=>'\\frac{1}{R_T} = \\frac{1}{R_1} + \\frac{1}{R_2}','desc'=>'Same voltage across all. Total < smallest.','vars'=>['Rₜ'=>'Total resistance'],'level'=>2,'sample_q'=>'4Ω∥4Ω = 2Ω'],
    ['id'=>'p30','name'=>'Electrical Power','latex'=>'P = IV = I^2R = \\frac{V^2}{R}','desc'=>'Three equivalent power formulas.','vars'=>['P'=>'Power (W)','I'=>'Current','V'=>'Voltage','R'=>'Resistance'],'level'=>2,'sample_q'=>'I=3A,V=12V: P=36W'],
    ['id'=>'p31','name'=>'Electrical Energy','latex'=>'E = Pt = IVt','desc'=>'Energy = power × time. 1 unit = 1kWh.','vars'=>['E'=>'Energy (J)','P'=>'Power (W)','t'=>'Time'],'level'=>1,'sample_q'=>'100W for 10h: E=1kWh'],
    ['id'=>'p32','name'=>'Transformer','latex'=>'\\frac{V_p}{V_s} = \\frac{N_p}{N_s} = \\frac{I_s}{I_p}','desc'=>'Ideal transformer: voltage and turns ratios.','vars'=>['Vp,Vs'=>'Primary/Secondary voltage','Np,Ns'=>'Turns'],'level'=>2,'sample_q'=>'Np=100,Ns=200,Vp=240V: Vs=480V'],
  ],
  'Modern Physics' => [
    ['id'=>'p33','name'=>'Photon Energy','latex'=>'E = hf = \\frac{hc}{\\lambda}','desc'=>'Energy of a photon. h=6.63×10⁻³⁴J·s.','vars'=>['E'=>'Energy (J)','h'=>'Planck constant','f'=>'Frequency','λ'=>'Wavelength'],'level'=>2,'sample_q'=>'f=6×10¹⁴Hz: E≈4×10⁻¹⁹J'],
    ['id'=>'p34','name'=>'Mass-Energy Equivalence','latex'=>'E = mc^2','desc'=>'Mass and energy are equivalent. c=3×10⁸m/s.','vars'=>['E'=>'Energy (J)','m'=>'Mass (kg)','c'=>'3×10⁸m/s'],'level'=>2,'sample_q'=>'Tiny mass → enormous energy'],
    ['id'=>'p35','name'=>'Radioactive Decay (Half-life)','latex'=>'N = N_0 \\left(\\frac{1}{2}\\right)^{t/t_{1/2}}','desc'=>'Undecayed nuclei after time t.','vars'=>['N'=>'Remaining','N₀'=>'Initial','t₁/₂'=>'Half-life','t'=>'Time'],'level'=>3,'sample_q'=>'After 2 half-lives: N=N₀/4'],
  ],
],
'chemistry' => [
  'Mole & Stoichiometry' => [
    ['id'=>'c1','name'=>'Moles from Mass','latex'=>'n = \\frac{m}{M}','desc'=>'Number of moles = mass ÷ molar mass.','vars'=>['n'=>'Moles (mol)','m'=>'Mass (g)','M'=>'Molar mass (g/mol)'],'level'=>1,'sample_q'=>'18g H₂O: M=18, n=1mol'],
    ['id'=>'c2','name'=>'Avogadro Particles','latex'=>'N = n \\times N_A, \\quad N_A = 6.02 \\times 10^{23}','desc'=>'Number of particles in n moles.','vars'=>['N'=>'Particles','n'=>'Moles','Nₐ'=>'6.02×10²³/mol'],'level'=>1,'sample_q'=>'2mol: N=1.204×10²⁴ molecules'],
    ['id'=>'c3','name'=>'Molar Volume (STP)','latex'=>'V = n \\times 22.4 \\text{ L} \\;(\\text{STP})','desc'=>'1 mole any gas = 22.4L at STP. 24L at r.t.p.','vars'=>['V'=>'Volume (L)','n'=>'Moles'],'level'=>1,'sample_q'=>'3mol CO₂ at STP: V=67.2L'],
    ['id'=>'c4','name'=>'Concentration','latex'=>'c = \\frac{n}{V}','desc'=>'Molar concentration. Volume in LITRES.','vars'=>['c'=>'Conc (mol/L)','n'=>'Moles','V'=>'Volume (L)'],'level'=>1,'sample_q'=>'0.5mol in 250mL: c=2mol/L'],
    ['id'=>'c5','name'=>'Titration Equivalence','latex'=>'\\frac{C_A V_A}{n_A} = \\frac{C_B V_B}{n_B}','desc'=>'At equivalence point. Use stoichiometric ratio from balanced equation.','vars'=>['C'=>'Concentration','V'=>'Volume','n'=>'Molar ratio'],'level'=>2,'sample_q'=>'H₂SO₄+2NaOH: CA×VA/1=CB×VB/2'],
    ['id'=>'c6','name'=>'Percentage Yield','latex'=>'\\%\\text{ yield} = \\frac{\\text{Actual}}{\\text{Theoretical}} \\times 100','desc'=>'How much of theoretical product was actually obtained.','vars'=>[],'level'=>2,'sample_q'=>'Actual=8g, Theoretical=10g: 80%'],
    ['id'=>'c7','name'=>'Empirical Formula Steps','latex'=>'\\text{ratio} = \\frac{\\%\\text{ mass}}{\\text{Molar mass}} \\rightarrow \\text{simplify}','desc'=>'Divide % composition by molar mass → simplest ratio.','vars'=>[],'level'=>2,'sample_q'=>'40%C,6.7%H,53.3%O → CH₂O'],
  ],
  'Gas Laws' => [
    ['id'=>'c8','name'=>'Ideal Gas Equation','latex'=>'PV = nRT','desc'=>'R=8.314J/mol·K. T must be in Kelvin.','vars'=>['P'=>'Pressure (Pa)','V'=>'Volume (m³)','n'=>'Moles','R'=>'8.314','T'=>'Kelvin'],'level'=>2,'sample_q'=>'n=1,T=273K: V≈22.4L at 101325Pa'],
    ['id'=>'c9','name'=>"Gay-Lussac's Law",'latex'=>'\\frac{P_1}{T_1} = \\frac{P_2}{T_2}','desc'=>'Constant volume: P proportional to T(Kelvin).','vars'=>['P'=>'Pressure','T'=>'Kelvin'],'level'=>1,'sample_q'=>'Double T(K) → double P'],
    ['id'=>'c10','name'=>"Dalton's Law",'latex'=>'P_{total} = P_1 + P_2 + P_3 + \\cdots','desc'=>'Total pressure = sum of partial pressures.','vars'=>['Pₜ'=>'Total pressure','P₁,P₂…'=>'Partial pressures'],'level'=>2,'sample_q'=>'P_air = P_N₂ + P_O₂ + P_Ar + …'],
  ],
  'Thermochemistry' => [
    ['id'=>'c11','name'=>'Enthalpy Change','latex'=>'\\Delta H = H_{\\text{products}} - H_{\\text{reactants}}','desc'=>'ΔH<0: exothermic. ΔH>0: endothermic.','vars'=>['ΔH'=>'Enthalpy change (kJ/mol)'],'level'=>1,'sample_q'=>'Combustion: ΔH always negative'],
    ['id'=>'c12','name'=>"Hess's Law",'latex'=>'\\Delta H_{\\text{rxn}} = \\sum \\Delta H_f(\\text{products}) - \\sum \\Delta H_f(\\text{reactants})','desc'=>'Enthalpy is path-independent. Use energy cycles.','vars'=>['ΔHf'=>'Standard enthalpy of formation'],'level'=>2,'sample_q'=>'Calculate ΔH of reactions you cannot do directly'],
    ['id'=>'c13','name'=>'Bond Energy','latex'=>'\\Delta H = \\Sigma E(\\text{broken}) - \\Sigma E(\\text{formed})','desc'=>'Breaking bonds absorbs energy; forming bonds releases.','vars'=>['E'=>'Bond energy (kJ/mol)'],'level'=>2,'sample_q'=>'ΔH = (+)broken − (−)formed'],
  ],
  'Equilibrium & Electrochemistry' => [
    ['id'=>'c14','name'=>'Equilibrium Constant Kc','latex'=>'K_c = \\frac{[C]^c[D]^d}{[A]^a[B]^b}','desc'=>'For aA+bB⇌cC+dD. Square brackets = molar concentration.','vars'=>['[X]'=>'Conc of X (mol/L)','a,b,c,d'=>'Stoichiometric coefficients'],'level'=>2,'sample_q'=>'N₂+3H₂⇌2NH₃: Kc=[NH₃]²/([N₂][H₂]³)'],
    ['id'=>'c15','name'=>'pH Definition','latex'=>'\\text{pH} = -\\log_{10}[\\text{H}^+]','desc'=>'pH<7: acid. pH=7: neutral. pH>7: base.','vars'=>['[H⁺]'=>'Hydrogen ion conc (mol/L)'],'level'=>2,'sample_q'=>'[H⁺]=0.01: pH=2'],
    ['id'=>'c16','name'=>'pH + pOH = 14','latex'=>'\\text{pH} + \\text{pOH} = 14','desc'=>'At 25°C. Knowing one gives the other instantly.','vars'=>['pH'=>'Acidity','pOH'=>'Basicity'],'level'=>2,'sample_q'=>'pH=3: pOH=11'],
    ['id'=>'c17','name'=>"Faraday's Law (Electrolysis)",'latex'=>'m = \\frac{ItM}{nF}, \\quad F = 96500 \\text{ C/mol}','desc'=>'Mass deposited during electrolysis.','vars'=>['m'=>'Mass (g)','I'=>'Current (A)','t'=>'Time (s)','M'=>'Molar mass','n'=>'Electrons','F'=>'96500C'],'level'=>2,'sample_q'=>'Deposit Cu(M=64,n=2),I=2A,t=2h: m≈4.8g'],
    ['id'=>'c18','name'=>'Cell EMF','latex'=>'E^\\circ_{\\text{cell}} = E^\\circ_{\\text{cathode}} - E^\\circ_{\\text{anode}}','desc'=>'Standard cell voltage. Positive = spontaneous reaction.','vars'=>['E°'=>'Standard electrode potential (V)'],'level'=>3,'sample_q'=>'Cu/Zn cell: 0.34-(-0.76)=1.10V'],
  ],
],
'biology' => [
  'Genetics & Inheritance' => [
    ['id'=>'b1','name'=>'Monohybrid F₂ Ratio','latex'=>'Aa \\times Aa \\rightarrow 1AA : 2Aa : 1aa \\;(3:1 \\text{ phenotype})','desc'=>'Cross of two heterozygotes. 3 dominant : 1 recessive phenotype.','vars'=>['A'=>'Dominant allele','a'=>'Recessive allele'],'level'=>1,'sample_q'=>'Tt × Tt → 75% tall, 25% short'],
    ['id'=>'b2','name'=>'Dihybrid F₂ Ratio','latex'=>'AaBb \\times AaBb \\rightarrow 9:3:3:1','desc'=>'Two independently assorting genes. Mendel\'s Law of Independent Assortment.','vars'=>['9'=>'Dominant both','3:3'=>'Dominant one each','1'=>'Recessive both'],'level'=>2,'sample_q'=>'9A_B_:3A_bb:3aaB_:1aabb'],
    ['id'=>'b3','name'=>'Hardy-Weinberg Equilibrium','latex'=>'p^2 + 2pq + q^2 = 1, \\quad p + q = 1','desc'=>'Allele frequencies in stable population (no evolution). p=dom freq, q=rec freq.','vars'=>['p²'=>'Homozygous dominant','2pq'=>'Heterozygous','q²'=>'Homozygous recessive'],'level'=>3,'sample_q'=>'q²=0.16 → q=0.4, p=0.6, carriers=2pq=48%'],
  ],
  'Ecology' => [
    ['id'=>'b4','name'=>'Population Growth Rate','latex'=>'r = b - d','desc'=>'Intrinsic rate of increase = birth rate - death rate.','vars'=>['r'=>'Growth rate','b'=>'Birth rate','d'=>'Death rate'],'level'=>1,'sample_q'=>'b=40,d=20 per 1000: r=20/1000'],
    ['id'=>'b5','name'=>'10% Energy Rule','latex'=>'\\text{Energy}_{n+1} = 10\\% \\times \\text{Energy}_n','desc'=>'Only ~10% of energy transfers between trophic levels. 90% lost as heat.','vars'=>['n'=>'Current trophic level'],'level'=>2,'sample_q'=>'1000kJ plants→100kJ herbivores→10kJ carnivores'],
    ['id'=>'b6','name'=>"Simpson's Diversity Index",'latex'=>'D = 1 - \\frac{\\sum n(n-1)}{N(N-1)}','desc'=>'D near 1 = high biodiversity. D near 0 = low biodiversity.','vars'=>['D'=>'Diversity index','n'=>'Count per species','N'=>'Total'],'level'=>3,'sample_q'=>'High D = healthy ecosystem'],
  ],
  'Physiology & Biochemistry' => [
    ['id'=>'b7','name'=>'Photosynthesis Equation','latex'=>'6CO_2 + 6H_2O \\xrightarrow{\\text{light}} C_6H_{12}O_6 + 6O_2','desc'=>'Light energy converts CO₂ and water into glucose and oxygen.','vars'=>['C₆H₁₂O₆'=>'Glucose','O₂'=>'Oxygen (by-product)'],'level'=>1,'sample_q'=>'O₂ produced; CO₂ absorbed'],
    ['id'=>'b8','name'=>'Aerobic Respiration','latex'=>'C_6H_{12}O_6 + 6O_2 \\rightarrow 6CO_2 + 6H_2O + \\text{ATP}','desc'=>'Complete oxidation of glucose releases ATP energy.','vars'=>['ATP'=>'Energy currency of the cell'],'level'=>1,'sample_q'=>'1 glucose → ~38 ATP molecules'],
    ['id'=>'b9','name'=>'ATP Hydrolysis','latex'=>'\\text{ATP} \\rightarrow \\text{ADP} + P_i + \\text{Energy}','desc'=>'Release of energy stored in the phosphate bond.','vars'=>['ATP'=>'Adenosine Triphosphate','ADP'=>'Adenosine Diphosphate','Pᵢ'=>'Inorganic phosphate'],'level'=>2,'sample_q'=>'Drives muscle contraction, active transport'],
    ['id'=>'b10','name'=>'Body Mass Index (BMI)','latex'=>'BMI = \\frac{\\text{mass (kg)}}{\\text{height (m)}^2}','desc'=>'Normal: 18.5–24.9. Overweight: 25–29.9. Obese: ≥30.','vars'=>['BMI'=>'kg/m²'],'level'=>1,'sample_q'=>'70kg, 1.75m: BMI≈22.9 (normal)'],
    ['id'=>'b11','name'=>'Cardiac Output','latex'=>'CO = HR \\times SV','desc'=>'Blood pumped per minute by the heart.','vars'=>['CO'=>'Cardiac output (mL/min)','HR'=>'Heart rate (beats/min)','SV'=>'Stroke volume (mL/beat)'],'level'=>2,'sample_q'=>'70bpm × 70mL = 4900mL/min ≈ 5L/min'],
  ],
],
]; // end $FORMULAS

$SUBJECT_META = [
  'mathematics' => ['icon'=>'fa-square-root-variable','label'=>'Mathematics','color'=>'#3b82f6','desc'=>'Algebra, Trigonometry, Calculus, Statistics'],
  'physics'     => ['icon'=>'fa-atom',                'label'=>'Physics',     'color'=>'#a78bfa','desc'=>'Mechanics, Waves, Electricity, Modern Physics'],
  'chemistry'   => ['icon'=>'fa-flask',               'label'=>'Chemistry',   'color'=>'#00c98a','desc'=>'Mole, Gas Laws, Equilibrium, Electrochemistry'],
  'biology'     => ['icon'=>'fa-dna',                 'label'=>'Biology',     'color'=>'#f59e0b','desc'=>'Genetics, Ecology, Physiology'],
];
$formulas_json = json_encode($FORMULAS, JSON_UNESCAPED_UNICODE);
$meta_json     = json_encode($SUBJECT_META, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Formula Panel — Excellent Simplified</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" onload="renderMathInPage()"></script>
<style>
:root{
  --bg:#04020e;--s1:#0d1017;--s2:#131720;--s3:#1a1f2e;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.14);
  --text:#e8ecf4;--sub:#8a93ab;--dim:#4a5268;
  --accent:#00c98a;--blue:#3b82f6;--amber:#f59e0b;--danger:#ff4757;--purple:#a78bfa;
  --sw:256px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;-webkit-font-smoothing:antialiased;overflow:hidden}
/* TOPBAR */
.topbar{height:54px;background:rgba(10,12,18,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 16px;gap:10px;position:fixed;top:0;left:0;right:0;z-index:100}
.tb-logo{width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;flex-shrink:0}
.tb-name{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.06em;flex:1}
.tb-right{display:flex;align-items:center;gap:7px}
.t-btn{height:30px;padding:0 10px;border-radius:7px;background:var(--s2);border:1px solid var(--border);color:var(--sub);cursor:pointer;font-size:11px;font-weight:600;display:flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s}
.t-btn:hover{color:var(--text);border-color:var(--border2)}
.user-chip{display:flex;align-items:center;gap:6px;padding:3px 10px;background:var(--s2);border:1px solid var(--border);border-radius:16px;font-size:11px;font-weight:600}
.user-chip img{width:20px;height:20px;border-radius:5px;object-fit:cover}
.user-av-sm{width:20px;height:20px;border-radius:5px;background:linear-gradient(135deg,var(--accent),var(--blue));display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;color:#000}
.sb-toggle{display:none;width:32px;height:32px;background:var(--s2);border:1px solid var(--border);border-radius:7px;color:var(--sub);cursor:pointer;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
/* LAYOUT */
.app{display:flex;height:100vh;padding-top:54px;overflow:hidden}
/* SIDEBAR */
.sidebar{width:var(--sw);flex-shrink:0;height:100%;background:var(--s1);border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;z-index:50;transition:transform .25s ease}
.subj-tabs{padding:10px 8px 4px;display:flex;flex-direction:column;gap:2px}
.subj-tab{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:9px;cursor:pointer;transition:all .15s;border:1px solid transparent}
.subj-tab:hover{background:var(--s2)}
.subj-tab.active{background:var(--s2);border-color:var(--border2)}
.tab-icon{width:28px;height:28px;border-radius:7px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px}
.tab-name{font-size:13px;font-weight:700;flex:1}
.tab-cnt{font-family:'Space Mono',monospace;font-size:9px;color:var(--dim)}
.topic-index{flex:1;overflow-y:auto;padding:0 8px 12px}
.topic-index::-webkit-scrollbar{width:3px}
.topic-index::-webkit-scrollbar-thumb{background:rgba(255,255,255,.05)}
.topic-btn{width:100%;display:flex;align-items:center;gap:7px;padding:7px 10px;border-radius:8px;border:none;background:none;color:var(--sub);cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:600;text-align:left;transition:all .15s}
.topic-btn:hover{background:var(--s2);color:var(--text)}
.topic-btn.active{background:var(--s2);color:var(--text)}
.t-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0;background:var(--dim);transition:background .15s}
.topic-btn.active .t-dot{background:var(--accent)}
.t-badge{margin-left:auto;font-family:'Space Mono',monospace;font-size:9px;color:var(--dim);background:var(--s3);padding:2px 5px;border-radius:4px}
/* MAIN */
.main{flex:1;display:flex;flex-direction:column;min-width:0;height:100%;overflow:hidden}
.main-head{padding:12px 16px 8px;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--s1)}
.search-row{display:flex;align-items:center;gap:8px;background:var(--s2);border:1px solid var(--border);border-radius:10px;padding:8px 12px;transition:border-color .15s;margin-bottom:8px}
.search-row:focus-within{border-color:rgba(59,130,246,.4)}
.search-row i{color:var(--dim);font-size:12px;flex-shrink:0}
#searchInput{background:none;border:none;outline:none;color:var(--text);font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;flex:1}
#searchInput::placeholder{color:var(--dim)}
.lf-row{display:flex;gap:5px;flex-wrap:wrap}
.lf-btn{padding:4px 11px;border-radius:20px;border:1px solid var(--border);background:var(--s2);cursor:pointer;font-size:11px;font-weight:700;color:var(--sub);transition:all .15s}
.lf-btn.active{border-color:rgba(59,130,246,.5);background:rgba(59,130,246,.1);color:var(--blue)}
.lf-btn.l1.active{border-color:rgba(0,201,138,.5);background:rgba(0,201,138,.08);color:var(--accent)}
.lf-btn.l2.active{border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.08);color:var(--amber)}
.lf-btn.l3.active{border-color:rgba(255,71,87,.4);background:rgba(255,71,87,.08);color:var(--danger)}
/* FEED */
.formula-feed{flex:1;overflow-y:auto;padding:14px 16px}
.formula-feed::-webkit-scrollbar{width:4px}
.formula-feed::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06);border-radius:4px}
.feed-topic-head{display:flex;align-items:center;gap:10px;margin:18px 0 8px;padding-bottom:7px;border-bottom:1px solid var(--border)}
.feed-topic-head:first-child{margin-top:0}
.fth-name{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.fth-line{flex:1;height:1px;background:var(--border)}
.fth-cnt{font-family:'Space Mono',monospace;font-size:9px;color:var(--dim)}
/* FORMULA CARD */
.fc{background:var(--s1);border:1px solid var(--border);border-radius:12px;margin-bottom:10px;overflow:hidden;transition:border-color .2s;animation:fcin .2s ease both}
@keyframes fcin{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
.fc:hover{border-color:var(--border2)}
.fc-head{padding:11px 13px 7px;display:flex;align-items:flex-start;gap:9px}
.fc-lvl{width:20px;height:20px;border-radius:5px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:700;font-family:'Space Mono',monospace;margin-top:1px}
.fc-lvl.l1{background:rgba(0,201,138,.12);color:var(--accent)}
.fc-lvl.l2{background:rgba(245,158,11,.1);color:var(--amber)}
.fc-lvl.l3{background:rgba(255,71,87,.08);color:var(--danger)}
.fc-name{font-size:14px;font-weight:700;color:var(--text);margin-bottom:2px}
.fc-desc{font-size:12px;color:var(--sub);line-height:1.5}
/* KaTeX display */
.fc-latex{padding:10px 14px;background:var(--s2);border-top:1px solid var(--border);border-bottom:1px solid var(--border);overflow-x:auto;text-align:center;min-height:44px;display:flex;align-items:center;justify-content:center}
.fc-latex .katex-display{margin:0!important}
/* Variables */
.fc-vars{padding:9px 13px 4px;display:flex;flex-wrap:wrap;gap:5px}
.vp{padding:3px 8px;border-radius:5px;background:var(--s3);font-size:11px;font-family:'Space Mono',monospace}
.vp .vs{color:var(--blue);font-weight:700}
.vp .vd{color:var(--sub)}
/* Sample */
.fc-sample{margin:5px 13px 0;padding:7px 11px;border-radius:7px;background:rgba(59,130,246,.05);border:1px solid rgba(59,130,246,.1);font-size:12px;color:var(--sub);line-height:1.5}
.fc-sample strong{color:var(--blue);font-size:10px;display:block;margin-bottom:2px;letter-spacing:.06em;text-transform:uppercase;font-family:'Space Mono',monospace}
/* Actions */
.fc-act{padding:9px 13px;display:flex;align-items:center;gap:6px}
.fc-ai{display:flex;align-items:center;gap:5px;padding:5px 12px;border-radius:7px;border:1px solid rgba(167,139,250,.3);background:rgba(167,139,250,.06);color:var(--purple);font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:all .15s}
.fc-ai:hover{background:rgba(167,139,250,.12);border-color:rgba(167,139,250,.5);transform:translateY(-1px)}
.fc-copy{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:7px;border:1px solid var(--border);background:var(--s2);color:var(--sub);font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s}
.fc-copy:hover{color:var(--text)}
.fc-lbl{margin-left:auto;font-family:'Space Mono',monospace;font-size:9px;color:var(--dim)}
/* EMPTY */
.empty-st{text-align:center;padding:60px 20px;color:var(--dim)}
.empty-st i{font-size:32px;margin-bottom:10px;display:block;opacity:.25}
.empty-st p{font-size:13px}
/* AI MODAL */
.ai-ov{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:14px}
.ai-ov.show{display:flex}
.ai-modal{width:100%;max-width:580px;background:var(--s1);border:1px solid var(--border);border-radius:16px;overflow:hidden;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.6);animation:aiPop .22s cubic-bezier(.22,1,.36,1) both}
@keyframes aiPop{from{opacity:0;transform:scale(.92) translateY(10px)}to{opacity:1;transform:none}}
.ai-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:9px;flex-shrink:0}
.ai-h-icon{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--purple),var(--blue));display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.ai-h-title{font-size:13px;font-weight:700;flex:1}
.ai-h-sub{font-size:11px;color:var(--sub);margin-top:1px}
.ai-close{width:28px;height:28px;border-radius:6px;background:var(--s2);border:1px solid var(--border);color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .15s}
.ai-close:hover{color:var(--text)}
.ai-formula-prev{padding:9px 14px;background:var(--s2);border-bottom:1px solid var(--border);overflow-x:auto;text-align:center;min-height:40px;display:flex;align-items:center;justify-content:center}
.ai-body{flex:1;overflow-y:auto;padding:14px 16px;min-height:100px}
.ai-body::-webkit-scrollbar{width:3px}
.ai-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,.06)}
.ai-load{display:flex;align-items:center;gap:9px;color:var(--sub);font-size:13px}
.ai-dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--purple);animation:aiDot 1.2s infinite;margin:0 2px}
.ai-dots span:nth-child(2){animation-delay:.2s}
.ai-dots span:nth-child(3){animation-delay:.4s}
@keyframes aiDot{0%,80%,100%{transform:scale(.6);opacity:.4}40%{transform:scale(1);opacity:1}}
.ai-resp{font-size:14px;line-height:1.75;color:var(--text);white-space:pre-wrap;word-break:break-word}
.ai-err{padding:10px 13px;border-radius:8px;background:rgba(255,71,87,.08);border:1px solid rgba(255,71,87,.2);color:var(--danger);font-size:13px;line-height:1.6}
.ai-err code{background:var(--s3);padding:1px 6px;border-radius:4px;font-family:'Space Mono',monospace;font-size:11px}
.ai-foot{padding:10px 14px;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:flex-end;gap:7px}
.ai-fb{padding:6px 14px;border-radius:7px;border:1px solid var(--border);background:var(--s2);color:var(--sub);font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:5px}
.ai-fb:hover{color:var(--text)}
.ai-fb.primary{background:linear-gradient(135deg,var(--purple),var(--blue));color:#fff;border-color:transparent;box-shadow:0 3px 10px rgba(167,139,250,.2)}
/* MOBILE */
@media(max-width:768px){
  :root{--sw:100%}
  .sidebar{position:fixed;left:0;top:54px;height:calc(100vh - 54px);transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .sb-toggle{display:inline-flex}
  .formula-feed{padding:10px 10px}
}
.katex{color:var(--text)!important}
</style>
</head>
<body>
<nav class="topbar">
  <button class="sb-toggle" id="sbToggle"><i class="fa fa-bars"></i></button>
  <div class="tb-logo">∑</div>
  <span class="tb-name">FORMULA PANEL</span>
  <div class="tb-right">
    <div class="user-chip">
      <?php if($display_picture):?><img src="<?=htmlspecialchars($display_picture)?>" alt=""><?php else:?><div class="user-av-sm"><?=htmlspecialchars(mb_strtoupper(mb_substr($display_name,0,1)))?></div><?php endif;?>
      <span><?=htmlspecialchars($display_name)?></span>
    </div>
    <a href="dashboard.php" class="t-btn"><i class="fa fa-house"></i></a>
    <a href="exams/practice_test.php" class="t-btn"><i class="fa fa-pen-nib"></i> Practice</a>
    <a href="ai/ai_helper.php" class="t-btn" style="color:var(--purple)"><i class="fa fa-robot"></i> AI</a>
  </div>
</nav>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="subj-tabs" id="subjTabs"></div>
    <div class="topic-index" id="topicIdx"></div>
  </aside>
  <div class="main">
    <div class="main-head">
      <div class="search-row">
        <i class="fa fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Search formulas… e.g. quadratic, momentum, pH, mole">
      </div>
      <div class="lf-row">
        <button class="lf-btn active" data-level="all">All</button>
        <button class="lf-btn l1" data-level="1">🟢 Easy</button>
        <button class="lf-btn l2" data-level="2">🟡 Medium</button>
        <button class="lf-btn l3" data-level="3">🔴 Hard</button>
      </div>
    </div>
    <div class="formula-feed" id="formulaFeed"></div>
  </div>
</div>
<!-- AI Modal -->
<div class="ai-ov" id="aiOv">
  <div class="ai-modal">
    <div class="ai-head">
      <div class="ai-h-icon">🤖</div>
      <div><div class="ai-h-title" id="aiTitle">AI Explanation</div><div class="ai-h-sub" id="aiSub">Powered by Claude</div></div>
      <button class="ai-close" onclick="closeAi()"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="ai-formula-prev" id="aiPrev"></div>
    <div class="ai-body" id="aiBody"><div class="ai-load"><div class="ai-dots"><span></span><span></span><span></span></div><span>Asking Claude AI…</span></div></div>
    <div class="ai-foot">
      <button class="ai-fb" onclick="closeAi()">Close</button>
      <button class="ai-fb primary" id="aiRetry" style="display:none" onclick="retryAi()"><i class="fa fa-rotate-right" style="font-size:10px"></i> Retry</button>
    </div>
  </div>
</div>
<script>
const FORMULAS = <?= $formulas_json ?>;
const SMETA    = <?= $meta_json ?>;
let activeSub  = 'mathematics';
let activeTopic = null;
let levelFilter = 'all';
let searchQ = '';
let curAiFormula = null;

function init(){ buildTabs(); buildTopics(); renderFeed(); }

function buildTabs(){
  const el = document.getElementById('subjTabs'); el.innerHTML='';
  Object.entries(SMETA).forEach(([k,m])=>{
    const tot = Object.values(FORMULAS[k]||{}).reduce((s,a)=>s+a.length,0);
    const d=document.createElement('div'); d.className='subj-tab'+(k===activeSub?' active':'');
    d.innerHTML=`<div class="tab-icon" style="background:${m.color}18;color:${m.color}"><i class="fa ${m.icon}"></i></div><div class="tab-name" style="${k===activeSub?'color:'+m.color:''}">${m.label}</div><div class="tab-cnt">${tot}</div>`;
    d.addEventListener('click',()=>{ activeSub=k; activeTopic=null; searchQ=''; document.getElementById('searchInput').value=''; buildTabs(); buildTopics(); renderFeed(); closeSbMob(); });
    el.appendChild(d);
  });
}

function buildTopics(){
  const el=document.getElementById('topicIdx'); el.innerHTML='';
  const topics=FORMULAS[activeSub]||{}; const meta=SMETA[activeSub];
  const tot=Object.values(topics).reduce((s,a)=>s+a.length,0);
  const ab=document.createElement('button'); ab.className='topic-btn'+(!activeTopic?' active':'');
  ab.innerHTML=`<span class="t-dot"></span>All Topics<span class="t-badge">${tot}</span>`;
  ab.addEventListener('click',()=>{ activeTopic=null; buildTopics(); renderFeed(); closeSbMob(); });
  el.appendChild(ab);
  Object.entries(topics).forEach(([name,fs])=>{
    const b=document.createElement('button'); b.className='topic-btn'+(activeTopic===name?' active':'');
    b.innerHTML=`<span class="t-dot" ${activeTopic===name?`style="background:${meta.color}"`:''}></span>${esc(name)}<span class="t-badge">${fs.length}</span>`;
    b.addEventListener('click',()=>{ activeTopic=name; buildTopics(); renderFeed(); closeSbMob(); });
    el.appendChild(b);
  });
}

function renderFeed(){
  const feed=document.getElementById('formulaFeed'); const meta=SMETA[activeSub];
  const topics=FORMULAS[activeSub]||{}; const q=searchQ.toLowerCase();
  const toShow=activeTopic?(topics[activeTopic]?{[activeTopic]:topics[activeTopic]}:{}) : topics;
  let html=''; let tot=0;
  Object.entries(toShow).forEach(([tname,fs])=>{
    const filtered=fs.filter(f=>{
      const ok=levelFilter==='all'||String(f.level)===levelFilter;
      const sq=!q||f.name.toLowerCase().includes(q)||f.desc.toLowerCase().includes(q)||f.latex.toLowerCase().includes(q)||(f.sample_q||'').toLowerCase().includes(q);
      return ok&&sq;
    });
    if(!filtered.length) return; tot+=filtered.length;
    html+=`<div class="feed-topic-head"><span class="fth-name" style="color:${meta.color}">${esc(tname)}</span><span class="fth-line"></span><span class="fth-cnt">${filtered.length}</span></div>`;
    filtered.forEach((f,i)=>{
      const lvlN=['','Easy','Medium','Hard'][f.level];
      const varsH=Object.entries(f.vars||{}).map(([s,d])=>`<span class="vp"><span class="vs">${esc(s)}</span><span class="vd"> — ${esc(d)}</span></span>`).join('');
      const fj=JSON.stringify(f).replace(/</g,'\\u003c');
      html+=`<div class="fc" style="animation-delay:${i*0.03}s">
        <div class="fc-head"><div class="fc-lvl l${f.level}">${f.level}</div><div><div class="fc-name">${esc(f.name)}</div><div class="fc-desc">${esc(f.desc)}</div></div></div>
        <div class="fc-latex">\\(${f.latex}\\)</div>
        ${varsH?`<div class="fc-vars">${varsH}</div>`:''}
        ${f.sample_q?`<div class="fc-sample"><strong>📌 JAMB example</strong>${esc(f.sample_q)}</div>`:''}
        <div class="fc-act">
          <button class="fc-ai" onclick='openAi(${fj})'><i class="fa fa-robot" style="font-size:10px"></i> Ask AI</button>
          <button class="fc-copy" onclick="copyF('${f.latex.replace(/'/g,"\\'")}',this)"><i class="fa fa-copy" style="font-size:10px"></i> Copy</button>
          <span class="fc-lbl">${lvlN}</span>
        </div></div>`;
    });
  });
  if(!tot) html='<div class="empty-st"><i class="fa fa-atom"></i><p>No formulas match.<br>Try another keyword or clear filters.</p></div>';
  feed.innerHTML=html;
  setTimeout(()=>renderMathInPage(),60);
}

function renderMathInPage(){
  if(typeof renderMathInElement==='undefined') return;
  renderMathInElement(document.getElementById('formulaFeed'),{
    delimiters:[{left:'\\(',right:'\\)',display:false},{left:'\\[',right:'\\]',display:true}],throwOnError:false
  });
  const aiPrev=document.getElementById('aiPrev');
  if(aiPrev&&document.getElementById('aiOv').classList.contains('show'))
    renderMathInElement(aiPrev,{delimiters:[{left:'\\(',right:'\\)',display:false}],throwOnError:false});
}

document.getElementById('searchInput').addEventListener('input',function(){ searchQ=this.value.trim(); activeTopic=null; buildTopics(); renderFeed(); });
document.querySelectorAll('.lf-btn').forEach(b=>b.addEventListener('click',function(){ document.querySelectorAll('.lf-btn').forEach(x=>x.classList.remove('active')); this.classList.add('active'); levelFilter=this.dataset.level; renderFeed(); }));

// AI
function openAi(f){
  curAiFormula=f;
  document.getElementById('aiTitle').textContent=f.name;
  document.getElementById('aiSub').textContent=SMETA[activeSub]?.label+' · '+['','Easy','Medium','Hard'][f.level];
  const prev=document.getElementById('aiPrev'); prev.textContent=''; prev.innerHTML=`\\(${f.latex}\\)`;
  document.getElementById('aiBody').innerHTML='<div class="ai-load"><div class="ai-dots"><span></span><span></span><span></span></div><span>Asking Claude AI…</span></div>';
  document.getElementById('aiRetry').style.display='none';
  document.getElementById('aiOv').classList.add('show');
  setTimeout(()=>{ if(typeof renderMathInElement!=='undefined') renderMathInElement(prev,{delimiters:[{left:'\\(',right:'\\)',display:false}],throwOnError:false}); },80);
  fetchAi(f);
}
function closeAi(){ document.getElementById('aiOv').classList.remove('show'); }
function retryAi(){ if(curAiFormula) openAi(curAiFormula); }
document.getElementById('aiOv').addEventListener('click',e=>{ if(e.target===document.getElementById('aiOv')) closeAi(); });

async function fetchAi(f){
  try{
    const fd=new FormData();
    fd.append('formula_name',f.name); fd.append('formula_tex',f.latex);
    fd.append('subject',SMETA[activeSub]?.label||activeSub); fd.append('description',f.desc||'');
    const res=await fetch('formula.php?action=explain',{method:'POST',body:fd});
    const j=await res.json();
    if(!j.success){ showAiErr(j.error||'AI unavailable'); return; }
    const body=document.getElementById('aiBody');
    body.innerHTML='<div class="ai-resp"></div>';
    typewrite(body.querySelector('.ai-resp'),j.explanation);
  }catch(e){ showAiErr('Network error — please try again.'); }
}
function showAiErr(msg){
  const body=document.getElementById('aiBody');
  if(msg.includes('not configured')){
    body.innerHTML=`<div class="ai-err">🔑 <strong>AI not configured</strong><br><br>Add <code>define('ANTHROPIC_API_KEY','sk-ant-...');</code> to your <code>config/db.php</code> file to enable AI explanations.<br><br>Meanwhile, visit the <a href="ai/ai_helper.php" style="color:var(--purple)">AI Tutor</a> and ask about <strong>${esc(curAiFormula?.name||'this formula')}</strong> there.</div>`;
  } else {
    body.innerHTML=`<div class="ai-err">⚠️ ${esc(msg)}</div>`;
  }
  document.getElementById('aiRetry').style.display='inline-flex';
}
function typewrite(el,text){ el.textContent=''; let i=0; const go=()=>{ if(i<text.length){ el.textContent+=text[i++]; document.getElementById('aiBody').scrollTop=9999; requestAnimationFrame(go); } }; go(); }

function copyF(latex,btn){
  navigator.clipboard.writeText(latex).then(()=>{ const o=btn.innerHTML; btn.innerHTML='<i class="fa fa-check" style="color:var(--accent)"></i> Copied'; setTimeout(()=>btn.innerHTML=o,1400); }).catch(()=>{});
}
function closeSbMob(){ if(window.innerWidth<=768) document.getElementById('sidebar').classList.remove('open'); }
document.getElementById('sbToggle').addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));
function esc(s){ return s?String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"}[c])):''; }
init();
</script>
</body>
</html>
